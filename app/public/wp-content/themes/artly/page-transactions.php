<?php
/**
 * Template Name: Transactions
 * Description: Wallet transactions & points ledger for the current user.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_user_logged_in() ) {
    $redirect_to = get_permalink();
    wp_safe_redirect( wp_login_url( $redirect_to ) );
    exit;
}

get_header();

$user_id = get_current_user_id();

$type_param = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : 'all';
$allowed_types = array( 'all', 'stock', 'ai', 'admin', 'other' );
if ( ! in_array( $type_param, $allowed_types, true ) ) {
    $type_param = 'all';
}

$page     = isset( $_GET['p'] ) ? max( 1, intval( $_GET['p'] ) ) : 1;
$per_page = 20;

$transactions = array(
    'items'       => array(),
    'total'       => 0,
    'total_pages' => 1,
);

if ( function_exists( 'nehtw_gateway_get_user_transactions' ) ) {
    $transactions = nehtw_gateway_get_user_transactions( $user_id, $page, $per_page, $type_param );
}

$items       = $transactions['items'];
$total_pages = ! empty( $transactions['total_pages'] ) ? (int) $transactions['total_pages'] : 1;

// CSV export link
$csv_url = '';
if ( function_exists( 'rest_url' ) ) {
    $csv_url = rest_url( 'nehtw/v1/wallet-transactions/export' );
    if ( $csv_url ) {
        $args = array();
        if ( 'all' !== $type_param ) {
            $args['type'] = $type_param;
        }
        if ( ! empty( $args ) ) {
            $csv_url = add_query_arg( $args, $csv_url );
        }
    }
}

?>

<main id="primary" class="site-main artly-transactions-page">
  <div class="artly-transactions-inner">
    <section class="transactions-hero">
      <p class="transactions-kicker"><?php esc_html_e( 'Billing', 'artly' ); ?></p>
      <h1 class="transactions-title"><?php esc_html_e( 'Transactions & wallet history', 'artly' ); ?></h1>
      <p class="transactions-subtitle">
        <?php esc_html_e( 'Review how your points were added and spent across AI generation, stock downloads and admin adjustments.', 'artly' ); ?>
      </p>
    </section>

    <section class="transactions-controls">
      <div class="transactions-filter-group">
        <?php
        $filters = array(
            'all'   => __( 'All', 'artly' ),
            'stock' => __( 'Stock', 'artly' ),
            'ai'    => __( 'AI', 'artly' ),
            'admin' => __( 'Admin', 'artly' ),
            'other' => __( 'Other', 'artly' ),
        );
        foreach ( $filters as $value => $label ) :
            $is_active = ( $type_param === $value );
            ?>
          <button
            type="button"
            class="transactions-filter-pill<?php echo $is_active ? ' is-active' : ''; ?>"
            data-type="<?php echo esc_attr( $value ); ?>"
          >
            <?php echo esc_html( $label ); ?>
          </button>
        <?php endforeach; ?>
      </div>
      <?php if ( $csv_url ) : ?>
        <a class="transactions-btn-export" href="<?php echo esc_url( $csv_url ); ?>">
          <?php esc_html_e( 'Export CSV', 'artly' ); ?>
        </a>
      <?php endif; ?>
    </section>

    <section class="transactions-card">
      <?php if ( ! empty( $items ) ) : ?>
        <table class="transactions-table">
          <thead>
            <tr>
              <th><?php esc_html_e( 'Date', 'artly' ); ?></th>
              <th><?php esc_html_e( 'Type', 'artly' ); ?></th>
              <th><?php esc_html_e( 'Description', 'artly' ); ?></th>
              <th><?php esc_html_e( 'Amount', 'artly' ); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $items as $item ) : ?>
              <?php
              $points     = isset( $item['points'] ) ? (float) $item['points'] : 0.0;
              $direction  = $points >= 0 ? 'credit' : 'debit';
              $amount_cls = $points >= 0 ? 'transactions-amount--credit' : 'transactions-amount--debit';

              $meta = isset( $item['meta'] ) && is_array( $item['meta'] ) ? $item['meta'] : array();
              $note   = isset( $meta['note'] ) ? $meta['note'] : '';
              $source = isset( $meta['source'] ) ? $meta['source'] : '';

              $type_raw = isset( $item['type'] ) ? $item['type'] : '';
              $type_label = $type_raw ? ucwords( str_replace( '_', ' ', $type_raw ) ) : __( 'Transaction', 'artly' );

              $created_at = isset( $item['created_at'] ) ? (int) $item['created_at'] : 0;
              $date_str   = $created_at
                  ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created_at )
                  : '';
              ?>
              <tr>
                <td data-label="<?php esc_attr_e( 'Date', 'artly' ); ?>">
                  <div class="transactions-meta">
                    <?php echo esc_html( $date_str ); ?>
                  </div>
                </td>
                <td data-label="<?php esc_attr_e( 'Type', 'artly' ); ?>">
                  <span class="transactions-type-pill">
                    <?php echo esc_html( $type_label ); ?>
                  </span>
                </td>
                <td data-label="<?php esc_attr_e( 'Description', 'artly' ); ?>">
                  <div class="transactions-note">
                    <?php
                    if ( $note ) {
                        echo esc_html( $note );
                    } else {
                        esc_html_e( 'Wallet update', 'artly' );
                    }
                    ?>
                  </div>
                  <?php if ( $source ) : ?>
                    <div class="transactions-meta">
                      <?php
                      printf(
                          esc_html__( 'Source: %s', 'artly' ),
                          esc_html( $source )
                      );
                      ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td data-label="<?php esc_attr_e( 'Amount', 'artly' ); ?>">
                  <div class="transactions-amount <?php echo esc_attr( $amount_cls ); ?>">
                    <?php echo $points >= 0 ? '+' : ''; ?>
                    <?php echo esc_html( number_format_i18n( $points, 2 ) ); ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if ( $total_pages > 1 ) : ?>
          <nav class="transactions-pagination" aria-label="<?php esc_attr_e( 'Transactions pagination', 'artly' ); ?>">
            <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
              <?php
              $url_args = array( 'p' => $i );
              if ( 'all' !== $type_param ) {
                  $url_args['type'] = $type_param;
              }
              $url        = add_query_arg( $url_args, get_permalink() );
              $is_current = ( $i === $page );
              ?>
              <a
                href="<?php echo esc_url( $url ); ?>"
                class="transactions-page-link<?php echo $is_current ? ' is-current' : ''; ?>"
              >
                <?php echo esc_html( $i ); ?>
              </a>
            <?php endfor; ?>
          </nav>
        <?php endif; ?>

      <?php else : ?>
        <p class="transactions-empty">
          <?php esc_html_e( 'No transactions found for this filter.', 'artly' ); ?>
        </p>
      <?php endif; ?>
    </section>
  </div>
</main>

<?php
get_footer();

