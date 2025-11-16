<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Subscription_Service {

    protected static $instance = null;

    public static function init() {
        if ( null !== self::$instance ) {
            return self::$instance;
        }

        if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_order' ) ) {
            return null;
        }

        self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'woocommerce_order_status_completed', array( $this, 'handle_order_completed' ), 25, 1 );
        add_action( 'woocommerce_order_status_processing', array( $this, 'handle_order_completed' ), 25, 1 );
        add_action( 'woocommerce_order_status_failed', array( $this, 'handle_order_failed' ), 10, 1 );
        add_action( 'woocommerce_order_status_cancelled', array( $this, 'handle_order_failed' ), 10, 1 );
    }

    public function handle_order_completed( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || ! $order->get_user_id() ) {
            return;
        }

        $this->inspect_order_for_subscriptions( $order );
    }

    public function handle_order_failed( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || ! $order->get_user_id() ) {
            return;
        }

        foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
            if ( ! $item instanceof WC_Order_Item_Product ) {
                continue;
            }

            $product_id = $item->get_product_id();
            if ( ! Nehtw_Subscription_Product_Helper::is_subscription_product( $product_id ) ) {
                continue;
            }

            $subscription = $this->get_subscription_from_item( $order, $item );
            if ( ! $subscription ) {
                continue;
            }

            $config = Nehtw_Subscription_Product_Helper::get_subscription_config( $product_id );
            $invoice_id = $this->maybe_mark_invoice_failed( $order, $item, $subscription, $config );

            $this->mark_subscription_overdue( $subscription );
            $this->record_history( $subscription['id'], 'payment_failed', array(
                'note' => sprintf( 'Payment failed for order #%d', $order->get_id() ),
            ) );

            if ( $invoice_id ) {
                do_action( 'nehtw_payment_failed', $invoice_id, array(
                    'order_id'        => $order->get_id(),
                    'subscription_id' => $subscription['id'],
                ) );
            }

            $item->add_meta_data( '_nehtw_subscription_id', $subscription['id'], true );
            if ( $invoice_id ) {
                $item->add_meta_data( '_nehtw_invoice_id', $invoice_id, true );
            }
            $item->save();
        }

        $order->add_order_note( __( 'Subscription payment failed – scheduled for retry/dunning.', 'nehtw-gateway' ) );
        $order->save();
    }

    protected function inspect_order_for_subscriptions( WC_Order $order ) {
        $processed = false;

        foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
            if ( ! $item instanceof WC_Order_Item_Product ) {
                continue;
            }

            $product_id = $item->get_product_id();
            if ( ! Nehtw_Subscription_Product_Helper::is_subscription_product( $product_id ) ) {
                continue;
            }

            $config = Nehtw_Subscription_Product_Helper::get_subscription_config( $product_id );
            if ( empty( $config['is_subscription'] ) || empty( $config['points'] ) ) {
                continue;
            }

            $subscription = $this->get_subscription_from_item( $order, $item );

            if ( $subscription ) {
                $this->handle_subscription_renewal( $order, $item, $config, $subscription );
            } else {
                $this->handle_initial_subscription_purchase( $order, $item, $config );
            }

            $processed = true;
        }

        if ( $processed ) {
            $order->save();
        }
    }

    protected function handle_initial_subscription_purchase( WC_Order $order, WC_Order_Item_Product $item, array $config ) {
        $user_id   = $order->get_user_id();
        $product_id = $item->get_product_id();
        $quantity  = max( 1, (int) $item->get_quantity() );
        $points    = (int) $config['points'] * $quantity;

        if ( $points <= 0 ) {
            return;
        }

        $plan_key        = $config['plan_key'];
        $paid_timestamp  = $this->get_order_paid_timestamp( $order );
        $next_timestamp  = $this->calculate_next_billing_timestamp( $paid_timestamp, $config['interval'], $config['interval_count'] );
        $meta            = array(
            'product_id'       => $product_id,
            'plan_name'        => $config['plan_name'],
            'interval_count'   => $config['interval_count'],
            'last_renewal_at'  => gmdate( 'Y-m-d H:i:s', $paid_timestamp ),
            'quantity'         => $quantity,
            'description'      => $config['description'],
        );

        $subscription_id = nehtw_gateway_save_subscription( array(
            'user_id'             => $user_id,
            'plan_key'            => $plan_key,
            'points_per_interval' => $points,
            'interval'            => $config['interval'],
            'status'              => 'active',
            'next_renewal_at'     => gmdate( 'Y-m-d H:i:s', $next_timestamp ),
            'meta'                => $meta,
        ) );

        if ( ! $subscription_id ) {
            return;
        }

        $this->update_subscription_payment_meta( $subscription_id, array(
            'payment_method'      => $order->get_payment_method(),
            'last_payment_attempt'=> gmdate( 'Y-m-d H:i:s', $paid_timestamp ),
            'failed_payment_count'=> 0,
            'dunning_level'       => 0,
        ) );

        $invoice_id = $this->create_invoice_for_order(
            $subscription_id,
            $order,
            $item,
            $paid_timestamp,
            $next_timestamp,
            'paid'
        );

        $transaction_id = $this->credit_wallet( $user_id, $subscription_id, $invoice_id, $points, $order );
        $this->record_usage( $user_id, $subscription_id, $invoice_id, $points, $paid_timestamp, $next_timestamp );
        $this->record_history( $subscription_id, 'created', array(
            'note' => sprintf( 'Subscription created from order #%d', $order->get_id() ),
        ) );

        $item->add_meta_data( '_nehtw_subscription_id', $subscription_id, true );
        $item->add_meta_data( '_nehtw_subscription_points', $points, true );
        if ( $invoice_id ) {
            $item->add_meta_data( '_nehtw_invoice_id', $invoice_id, true );
        }
        $item->save();

        if ( $invoice_id ) {
            do_action( 'nehtw_invoice_paid', $invoice_id );
        }
        do_action( 'nehtw_subscription_created', $subscription_id, $order->get_id(), $transaction_id );

        $order->add_order_note( sprintf( __( 'Subscription #%1$d activated and wallet credited with %2$d points.', 'nehtw-gateway' ), $subscription_id, $points ) );
    }

    protected function handle_subscription_renewal( WC_Order $order, WC_Order_Item_Product $item, array $config, array $subscription ) {
        $invoice_id = absint( $item->get_meta( '_nehtw_invoice_id', true ) );
        // TODO: Add proration support when plan terms change mid-cycle.
        if ( $invoice_id ) {
            $existing = $this->get_invoice( $invoice_id );
            if ( $existing && 'paid' === $existing['status'] ) {
                return;
            }
        }

        $paid_timestamp = $this->get_order_paid_timestamp( $order );
        $interval_count = $this->get_interval_count_from_subscription( $subscription, $config['interval_count'] );
        $period_start   = $this->get_subscription_last_renewal_timestamp( $subscription, $paid_timestamp );
        $period_end     = $this->calculate_next_billing_timestamp( $paid_timestamp, $subscription['interval'], $interval_count );
        $points         = (int) $subscription['points_per_interval'];
        if ( $points <= 0 ) {
            $quantity = max( 1, (int) $item->get_quantity() );
            $points   = (int) $config['points'] * $quantity;
        }

        if ( $invoice_id ) {
            $this->update_invoice_to_paid( $invoice_id, $order, $item, $period_start, $period_end );
        } else {
            $invoice_id = $this->create_invoice_for_order( $subscription['id'], $order, $item, $period_start, $period_end, 'paid' );
        }

        $transaction_id = $this->credit_wallet( $subscription['user_id'], $subscription['id'], $invoice_id, $points, $order );
        $this->record_usage( $subscription['user_id'], $subscription['id'], $invoice_id, $points, $period_start, $period_end );

        $meta = $this->get_subscription_meta_array( $subscription );
        $meta['last_renewal_at'] = gmdate( 'Y-m-d H:i:s', $paid_timestamp );
        $meta['interval_count']  = $interval_count;
        $meta['plan_name']       = $config['plan_name'];

        $this->update_subscription_payment_meta( $subscription['id'], array(
            'status'              => 'active',
            'next_renewal_at'     => gmdate( 'Y-m-d H:i:s', $period_end ),
            'meta'                => $meta,
            'failed_payment_count'=> 0,
            'dunning_level'       => 0,
            'last_payment_attempt'=> gmdate( 'Y-m-d H:i:s', $paid_timestamp ),
            'payment_method'      => $order->get_payment_method(),
        ) );

        $item->add_meta_data( '_nehtw_subscription_id', $subscription['id'], true );
        $item->add_meta_data( '_nehtw_subscription_points', $points, true );
        if ( $invoice_id ) {
            $item->add_meta_data( '_nehtw_invoice_id', $invoice_id, true );
        }
        $item->save();

        if ( $invoice_id ) {
            do_action( 'nehtw_invoice_paid', $invoice_id );
        }

        do_action( 'nehtw_subscription_renewed', $subscription['id'], $invoice_id, $order->get_id(), $transaction_id );

        $order->add_order_note( sprintf( __( 'Subscription #%1$d renewed. %2$d points credited.', 'nehtw-gateway' ), $subscription['id'], $points ) );
    }

    protected function maybe_mark_invoice_failed( WC_Order $order, WC_Order_Item_Product $item, array $subscription, array $config ) {
        $invoice_id = absint( $item->get_meta( '_nehtw_invoice_id', true ) );
        $period_start = $this->get_subscription_last_renewal_timestamp( $subscription, $this->get_order_paid_timestamp( $order ) );
        $interval_count = $this->get_interval_count_from_subscription( $subscription, $config['interval_count'] );
        $period_end   = $this->calculate_next_billing_timestamp( $period_start, $subscription['interval'], $interval_count );

        if ( $invoice_id ) {
            $this->mark_invoice_failed( $invoice_id );
            return $invoice_id;
        }

        return $this->create_invoice_for_order( $subscription['id'], $order, $item, $period_start, $period_end, 'failed' );
    }

    protected function create_invoice_for_order( $subscription_id, WC_Order $order, WC_Order_Item_Product $item, $period_start, $period_end, $status = 'paid' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'nehtw_invoices';

        $amount      = (float) $item->get_total();
        $tax_amount  = (float) $item->get_total_tax();
        $total       = $amount + $tax_amount;
        $now         = current_time( 'mysql' );
        $paid_at     = 'paid' === $status ? gmdate( 'Y-m-d H:i:s', $this->get_order_paid_timestamp( $order ) ) : null;
        $invoice_number = $this->generate_invoice_number();

        $meta = array(
            'order_id'      => $order->get_id(),
            'order_item_id' => $item->get_id(),
            'points'        => $item->get_meta( '_nehtw_subscription_points', true ) ?: null,
        );

        $inserted = $wpdb->insert(
            $table,
            array(
                'subscription_id'     => $subscription_id,
                'user_id'             => $order->get_user_id(),
                'invoice_number'      => $invoice_number,
                'amount'              => $amount,
                'tax_amount'          => $tax_amount,
                'total_amount'        => $total,
                'currency'            => $order->get_currency(),
                'status'              => $status,
                'billing_period_start'=> gmdate( 'Y-m-d H:i:s', $period_start ),
                'billing_period_end'  => gmdate( 'Y-m-d H:i:s', $period_end ),
                'due_date'            => gmdate( 'Y-m-d H:i:s', $period_start ),
                'paid_at'             => $paid_at,
                'payment_method'      => $order->get_payment_method(),
                'payment_gateway'     => $order->get_payment_method_title(),
                'gateway_transaction_id' => $order->get_transaction_id(),
                'meta'                => wp_json_encode( $meta ),
                'created_at'          => $now,
                'updated_at'          => $now,
            ),
            array( '%d','%d','%s','%f','%f','%f','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' )
        );

        if ( ! $inserted ) {
            return 0;
        }

        $invoice_id = (int) $wpdb->insert_id;
        do_action( 'nehtw_invoice_created', $invoice_id );
        return $invoice_id;
    }

    protected function update_invoice_to_paid( $invoice_id, WC_Order $order, WC_Order_Item_Product $item, $period_start, $period_end ) {
        global $wpdb;
        $table = $wpdb->prefix . 'nehtw_invoices';
        $meta  = array(
            'order_id'      => $order->get_id(),
            'order_item_id' => $item->get_id(),
        );

        $wpdb->update(
            $table,
            array(
                'amount'              => (float) $item->get_total(),
                'tax_amount'          => (float) $item->get_total_tax(),
                'total_amount'        => (float) $item->get_total() + (float) $item->get_total_tax(),
                'status'              => 'paid',
                'billing_period_start'=> gmdate( 'Y-m-d H:i:s', $period_start ),
                'billing_period_end'  => gmdate( 'Y-m-d H:i:s', $period_end ),
                'paid_at'             => gmdate( 'Y-m-d H:i:s', $this->get_order_paid_timestamp( $order ) ),
                'payment_method'      => $order->get_payment_method(),
                'payment_gateway'     => $order->get_payment_method_title(),
                'gateway_transaction_id' => $order->get_transaction_id(),
                'meta'                => wp_json_encode( $meta ),
                'updated_at'          => current_time( 'mysql' ),
            ),
            array( 'id' => $invoice_id ),
            array( '%f','%f','%f','%s','%s','%s','%s','%s','%s','%s','%s' ),
            array( '%d' )
        );
    }

    protected function mark_invoice_failed( $invoice_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'nehtw_invoices';
        $wpdb->update(
            $table,
            array(
                'status'     => 'failed',
                'paid_at'    => null,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $invoice_id ),
            array( '%s','%s','%s' ),
            array( '%d' )
        );
    }

    protected function credit_wallet( $user_id, $subscription_id, $invoice_id, $points, WC_Order $order ) {
        if ( ! function_exists( 'nehtw_gateway_add_transaction' ) ) {
            return 0;
        }

        return nehtw_gateway_add_transaction(
            $user_id,
            'subscription_credit',
            $points,
            array(
                'meta' => array(
                    'subscription_id' => $subscription_id,
                    'invoice_id'      => $invoice_id,
                    'order_id'        => $order->get_id(),
                ),
            )
        );
    }

    protected function record_usage( $user_id, $subscription_id, $invoice_id, $points, $period_start, $period_end ) {
        global $wpdb;
        $table = $wpdb->prefix . 'nehtw_usage_tracking';
        $wpdb->insert(
            $table,
            array(
                'user_id'             => $user_id,
                'subscription_id'     => $subscription_id,
                'usage_type'          => 'subscription_credit',
                'amount'              => $points,
                'unit'                => 'points',
                'recorded_at'         => current_time( 'mysql' ),
                'billing_period_start'=> gmdate( 'Y-m-d H:i:s', $period_start ),
                'billing_period_end'  => gmdate( 'Y-m-d H:i:s', $period_end ),
                'meta'                => wp_json_encode( array( 'invoice_id' => $invoice_id ) ),
            ),
            array( '%d','%d','%s','%f','%s','%s','%s','%s','%s' )
        );
    }

    protected function record_history( $subscription_id, $action, $data = array() ) {
        global $wpdb;
        $subscription = nehtw_gateway_get_subscription( $subscription_id );
        if ( ! $subscription ) {
            return;
        }

        $wpdb->insert(
            $wpdb->prefix . 'nehtw_subscription_history',
            array(
                'subscription_id' => $subscription_id,
                'user_id'         => $subscription['user_id'],
                'action'          => $action,
                'note'            => isset( $data['note'] ) ? $data['note'] : null,
                'meta'            => wp_json_encode( $data ),
                'created_at'      => current_time( 'mysql' ),
                'created_by'      => get_current_user_id(),
            ),
            array( '%d','%d','%s','%s','%s','%s','%d' )
        );
    }

    protected function get_subscription_from_item( WC_Order $order, WC_Order_Item_Product $item ) {
        $subscription_id = absint( $item->get_meta( '_nehtw_subscription_id', true ) );
        if ( $subscription_id ) {
            $subscription = nehtw_gateway_get_subscription( $subscription_id );
            if ( $subscription ) {
                return $subscription;
            }
        }

        $plan_key = Nehtw_Subscription_Product_Helper::get_plan_key_for_product( $item->get_product_id() );
        return nehtw_gateway_get_subscription_by_plan( $order->get_user_id(), $plan_key );
    }

    protected function update_subscription_payment_meta( $subscription_id, array $data ) {
        global $wpdb;
        $table = nehtw_gateway_get_subscriptions_table();
        if ( ! $table ) {
            return;
        }

        $fields = array();
        $formats = array();

        foreach ( array( 'payment_method', 'status', 'next_renewal_at', 'last_payment_attempt' ) as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $fields[ $field ] = $data[ $field ];
                $formats[] = '%s';
            }
        }

        if ( isset( $data['failed_payment_count'] ) ) {
            $fields['failed_payment_count'] = (int) $data['failed_payment_count'];
            $formats[] = '%d';
        }

        if ( isset( $data['dunning_level'] ) ) {
            $fields['dunning_level'] = (int) $data['dunning_level'];
            $formats[] = '%d';
        }

        if ( isset( $data['meta'] ) ) {
            $fields['meta'] = wp_json_encode( $data['meta'] );
            $formats[] = '%s';
        }

        if ( empty( $fields ) ) {
            return;
        }

        $fields['updated_at'] = current_time( 'mysql' );
        $formats[] = '%s';

        $wpdb->update( $table, $fields, array( 'id' => $subscription_id ), $formats, array( '%d' ) );
    }

    protected function mark_subscription_overdue( array $subscription ) {
        $failed_count = isset( $subscription['failed_payment_count'] ) ? (int) $subscription['failed_payment_count'] : 0;
        $failed_count++;

        $meta = $this->get_subscription_meta_array( $subscription );
        $this->update_subscription_payment_meta( $subscription['id'], array(
            'status'              => 'overdue',
            'failed_payment_count'=> $failed_count,
            'last_payment_attempt'=> current_time( 'mysql' ),
            'meta'                => $meta,
        ) );
    }

    protected function get_subscription_meta_array( $subscription ) {
        if ( empty( $subscription['meta'] ) ) {
            return array();
        }

        if ( is_array( $subscription['meta'] ) ) {
            return $subscription['meta'];
        }

        $decoded = json_decode( $subscription['meta'], true );
        return is_array( $decoded ) ? $decoded : array();
    }

    protected function get_subscription_last_renewal_timestamp( $subscription, $fallback ) {
        $meta = $this->get_subscription_meta_array( $subscription );
        if ( ! empty( $meta['last_renewal_at'] ) ) {
            $ts = strtotime( $meta['last_renewal_at'] );
            if ( $ts ) {
                return $ts;
            }
        }
        return $fallback;
    }

    protected function get_interval_count_from_subscription( $subscription, $default ) {
        $meta = $this->get_subscription_meta_array( $subscription );
        if ( ! empty( $meta['interval_count'] ) ) {
            return max( 1, (int) $meta['interval_count'] );
        }
        return max( 1, (int) $default );
    }

    protected function calculate_next_billing_timestamp( $timestamp, $interval, $interval_count ) {
        $interval_count = max( 1, (int) $interval_count );
        $date = new DateTime( gmdate( 'Y-m-d H:i:s', $timestamp ), new DateTimeZone( 'UTC' ) );
        $date->modify( '+' . $interval_count . ' ' . $interval );
        return $date->getTimestamp();
    }

    protected function get_order_paid_timestamp( WC_Order $order ) {
        $date = $order->get_date_paid();
        if ( $date ) {
            return $date->getTimestamp();
        }
        $completed = $order->get_date_completed();
        if ( $completed ) {
            return $completed->getTimestamp();
        }
        return current_time( 'timestamp', true );
    }

    protected function get_invoice( $invoice_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nehtw_invoices WHERE id = %d", $invoice_id ), ARRAY_A );
    }

    protected function generate_invoice_number() {
        return 'INV-' . gmdate( 'YmdHis' ) . '-' . wp_rand( 1000, 9999 );
    }
}
