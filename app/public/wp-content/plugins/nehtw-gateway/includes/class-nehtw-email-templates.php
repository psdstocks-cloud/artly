<?php
/**
 * Email template management for subscription reminders
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get all email templates as an associative array by trigger key.
 *
 * @return array
 */
function nehtw_gateway_get_email_templates() {
    $templates = get_option( 'nehtw_gateway_email_templates', array() );

    if ( ! is_array( $templates ) ) {
        $templates = array();
    }

    // Ensure we have default structure for our two triggers.
    $defaults = array(
        'subscription_renewal_3d' => array(
            'enabled'     => true,
            'subject'     => __( 'Your Artly plan renews in 3 days', 'nehtw-gateway' ),
            'body_html'   => '<p>Hi {{user_name}},</p><p>Your Artly subscription "{{plan_name}}" is set to renew on <strong>{{next_renewal_date}}</strong>.</p><p>You\'ll receive {{points_per_interval}} points on renewal. If you need to change or cancel your plan, just visit your account.</p><p>Thanks,<br>{{site_name}}</p>',
            'description' => __( 'Sent 3 days before subscription renewal.', 'nehtw-gateway' ),
        ),
        'subscription_renewal_1d' => array(
            'enabled'     => true,
            'subject'     => __( 'Your Artly plan renews tomorrow', 'nehtw-gateway' ),
            'body_html'   => '<p>Hi {{user_name}},</p><p>This is a quick reminder that your Artly subscription "{{plan_name}}" will renew tomorrow (<strong>{{next_renewal_date}}</strong>).</p><p>You will receive {{points_per_interval}} points as part of this renewal.</p><p>Thanks,<br>{{site_name}}</p>',
            'description' => __( 'Sent 1 day before subscription renewal.', 'nehtw-gateway' ),
        ),
    );

    foreach ( $defaults as $key => $value ) {
        if ( ! isset( $templates[ $key ] ) || ! is_array( $templates[ $key ] ) ) {
            $templates[ $key ] = $value;
        } else {
            $templates[ $key ] = wp_parse_args( $templates[ $key ], $value );
        }
    }

    return $templates;
}

/**
 * Save email templates.
 *
 * @param array $templates
 */
function nehtw_gateway_save_email_templates( $templates ) {
    if ( ! is_array( $templates ) ) {
        $templates = array();
    }

    update_option( 'nehtw_gateway_email_templates', $templates );
}

/**
 * Send a templated email for a subscription reminder.
 *
 * @param string $trigger_key  'subscription_renewal_3d' or 'subscription_renewal_1d'
 * @param int    $user_id
 * @param array  $subscription_row  Row from subscriptions table.
 *
 * @return bool True if email was sent, false otherwise.
 */
function nehtw_gateway_send_subscription_email_templated( $trigger_key, $user_id, $subscription_row ) {
    $templates = nehtw_gateway_get_email_templates();

    if ( empty( $templates[ $trigger_key ] ) ) {
        return false;
    }

    $tpl = $templates[ $trigger_key ];

    if ( empty( $tpl['enabled'] ) ) {
        return false;
    }

    $user = get_userdata( $user_id );

    if ( ! $user ) {
        return false;
    }

    $plan_key  = isset( $subscription_row['plan_key'] ) ? $subscription_row['plan_key'] : '';
    $meta      = array();

    if ( ! empty( $subscription_row['meta'] ) && is_string( $subscription_row['meta'] ) ) {
        $decoded = json_decode( $subscription_row['meta'], true );
        if ( is_array( $decoded ) ) {
            $meta = $decoded;
        }
    } elseif ( is_array( $subscription_row['meta'] ) ) {
        $meta = $subscription_row['meta'];
    }

    $plan_name = isset( $meta['plan_name'] ) ? $meta['plan_name'] : $plan_key;
    $points    = isset( $subscription_row['points_per_interval'] ) ? (float) $subscription_row['points_per_interval'] : 0.0;
    $next_raw  = isset( $subscription_row['next_renewal_at'] ) ? $subscription_row['next_renewal_at'] : '';
    $next_ts   = $next_raw ? strtotime( $next_raw ) : 0;
    $next_formatted = $next_ts
        ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_ts )
        : $next_raw;

    $dashboard_page = get_page_by_path( 'dashboard' );
    $dashboard_url  = $dashboard_page ? get_permalink( $dashboard_page->ID ) : home_url( '/dashboard/' );

    $replacements = array(
        '{{user_name}}'          => $user->display_name,
        '{{user_email}}'         => $user->user_email,
        '{{plan_name}}'          => $plan_name,
        '{{next_renewal_date}}'  => $next_formatted,
        '{{points_per_interval}}'=> number_format_i18n( $points, 2 ),
        '{{site_name}}'          => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
        '{{dashboard_url}}'      => $dashboard_url,
    );

    $subject = $tpl['subject'];
    $body    = $tpl['body_html'];

    foreach ( $replacements as $tag => $value ) {
        $subject = str_replace( $tag, $value, $subject );
        $body    = str_replace( $tag, $value, $body );
    }

    $headers = array( 'Content-Type: text/html; charset=UTF-8' );

    return wp_mail( $user->user_email, $subject, $body, $headers );
}

/**
 * Process subscription reminders (3 days and 1 day before renewal).
 */
function nehtw_gateway_process_subscription_reminders() {
    global $wpdb;

    $table = nehtw_gateway_get_subscriptions_table();

    if ( ! $table ) {
        return;
    }

    $now    = current_time( 'mysql', true );
    $now_ts = strtotime( $now );

    // Fetch all active subscriptions.
    $sql = $wpdb->prepare(
        "SELECT * FROM {$table} WHERE status = %s",
        'active'
    );

    $subs = $wpdb->get_results( $sql, ARRAY_A );

    if ( empty( $subs ) ) {
        return;
    }

    foreach ( $subs as $sub ) {
        $user_id = (int) $sub['user_id'];
        $next    = isset( $sub['next_renewal_at'] ) ? $sub['next_renewal_at'] : '';

        if ( ! $user_id || ! $next ) {
            continue;
        }

        $next_ts = strtotime( $next );

        if ( ! $next_ts ) {
            continue;
        }

        $diff_seconds = $next_ts - $now_ts;
        $days = (int) floor( $diff_seconds / DAY_IN_SECONDS );

        // Load/normalize meta as array
        $meta = array();
        if ( ! empty( $sub['meta'] ) ) {
            if ( is_string( $sub['meta'] ) ) {
                $decoded = json_decode( $sub['meta'], true );
                if ( is_array( $decoded ) ) {
                    $meta = $decoded;
                }
            } elseif ( is_array( $sub['meta'] ) ) {
                $meta = $sub['meta'];
            }
        }

        $reminders_sent = isset( $meta['reminders_sent'] ) && is_array( $meta['reminders_sent'] )
            ? $meta['reminders_sent']
            : array();

        $updated_meta = false;

        // 3 days before
        if ( 3 === $days && empty( $reminders_sent['subscription_renewal_3d'] ) ) {
            $sent = nehtw_gateway_send_subscription_email_templated( 'subscription_renewal_3d', $user_id, $sub );

            if ( $sent ) {
                $reminders_sent['subscription_renewal_3d'] = true;
                $updated_meta = true;
            }
        }

        // 1 day before
        if ( 1 === $days && empty( $reminders_sent['subscription_renewal_1d'] ) ) {
            $sent = nehtw_gateway_send_subscription_email_templated( 'subscription_renewal_1d', $user_id, $sub );

            if ( $sent ) {
                $reminders_sent['subscription_renewal_1d'] = true;
                $updated_meta = true;
            }
        }

        if ( $updated_meta ) {
            $meta['reminders_sent'] = $reminders_sent;

            $wpdb->update(
                $table,
                array(
                    'meta'       => wp_json_encode( $meta ),
                    'updated_at' => $now,
                ),
                array( 'id' => $sub['id'] ),
                array( '%s', '%s' ),
                array( '%d' )
            );
        }
    }
}

/**
 * Render the Email Templates admin page.
 */
function nehtw_gateway_render_email_templates_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'nehtw-gateway' ) );
    }

    $templates = nehtw_gateway_get_email_templates();

    if ( isset( $_POST['nehtw_gateway_email_templates_nonce'] ) &&
         wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nehtw_gateway_email_templates_nonce'] ) ), 'nehtw_gateway_email_templates' )
    ) {
        $posted = isset( $_POST['templates'] ) ? (array) $_POST['templates'] : array();

        foreach ( $templates as $key => &$tpl ) {
            if ( ! isset( $posted[ $key ] ) ) {
                continue;
            }

            $tpl_data = $posted[ $key ];

            $tpl['enabled']   = ! empty( $tpl_data['enabled'] );
            $tpl['subject']   = isset( $tpl_data['subject'] ) ? wp_kses_post( wp_unslash( $tpl_data['subject'] ) ) : '';
            $tpl['body_html'] = isset( $tpl_data['body_html'] ) ? wp_kses_post( wp_unslash( $tpl_data['body_html'] ) ) : '';
        }

        nehtw_gateway_save_email_templates( $templates );

        echo '<div class="updated"><p>' . esc_html__( 'Email templates updated.', 'nehtw-gateway' ) . '</p></div>';

        // Reload templates after save
        $templates = nehtw_gateway_get_email_templates();
    }

    ?>
    <div class="wrap">
      <h1><?php esc_html_e( 'Email Templates', 'nehtw-gateway' ); ?></h1>
      <p><?php esc_html_e( 'Customize the emails Artly sends before subscriptions renew. You can use placeholders like {{user_name}}, {{plan_name}}, {{next_renewal_date}}.', 'nehtw-gateway' ); ?></p>
      <form method="post">
        <?php wp_nonce_field( 'nehtw_gateway_email_templates', 'nehtw_gateway_email_templates_nonce' ); ?>
        <?php foreach ( $templates as $key => $tpl ) : ?>
          <hr />
          <h2>
            <?php
            if ( 'subscription_renewal_3d' === $key ) {
                esc_html_e( 'Subscription renewal – 3 days before', 'nehtw-gateway' );
            } elseif ( 'subscription_renewal_1d' === $key ) {
                esc_html_e( 'Subscription renewal – 1 day before', 'nehtw-gateway' );
            } else {
                echo esc_html( $key );
            }
            ?>
          </h2>
          <?php if ( ! empty( $tpl['description'] ) ) : ?>
            <p class="description"><?php echo esc_html( $tpl['description'] ); ?></p>
          <?php endif; ?>
          <p>
            <label>
              <input type="checkbox" name="templates[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( ! empty( $tpl['enabled'] ) ); ?> />
              <?php esc_html_e( 'Enable this email', 'nehtw-gateway' ); ?>
            </label>
          </p>
          <p>
            <label for="tpl-<?php echo esc_attr( $key ); ?>-subject">
              <?php esc_html_e( 'Subject', 'nehtw-gateway' ); ?>
            </label><br />
            <input type="text" id="tpl-<?php echo esc_attr( $key ); ?>-subject" name="templates[<?php echo esc_attr( $key ); ?>][subject]" value="<?php echo esc_attr( $tpl['subject'] ); ?>" class="regular-text" style="width: 100%; max-width: 600px;" />
          </p>
          <p>
            <label for="tpl-<?php echo esc_attr( $key ); ?>-body">
              <?php esc_html_e( 'HTML body', 'nehtw-gateway' ); ?>
            </label>
          </p>
          <?php
          $editor_id = 'tpl_' . $key . '_body_html';
          wp_editor(
              $tpl['body_html'],
              $editor_id,
              array(
                  'textarea_name' => 'templates[' . esc_attr( $key ) . '][body_html]',
                  'textarea_rows' => 10,
                  'media_buttons' => false,
              )
          );
          ?>
        <?php endforeach; ?>
        <p class="description">
          <strong><?php esc_html_e( 'Available placeholders:', 'nehtw-gateway' ); ?></strong><br />
          <?php esc_html_e( '{{user_name}}, {{user_email}}, {{plan_name}}, {{next_renewal_date}}, {{points_per_interval}}, {{site_name}}, {{dashboard_url}}', 'nehtw-gateway' ); ?>
        </p>
        <?php submit_button( __( 'Save templates', 'nehtw-gateway' ) ); ?>
      </form>
    </div>
    <?php
}

