<?php
/**
 * Wallet top-up modal partial.
 * Internal plugin helpers are used, but no "nehtw" text appears in the markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$packs = function_exists( 'nehtw_gateway_get_wallet_topup_packs' )
    ? nehtw_gateway_get_wallet_topup_packs()
    : array();

?>

<div class="artly-modal artly-modal--wallet" data-artly-modal="wallet-topup" aria-hidden="true">
  <div class="artly-modal-backdrop" data-artly-modal-close></div>
  <div class="artly-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="wallet-modal-title">
    <button type="button" class="artly-modal-close" data-artly-modal-close aria-label="<?php esc_attr_e( 'Close', 'artly' ); ?>">
      ×
    </button>
    <div class="artly-modal-header">
      <p class="artly-modal-kicker"><?php esc_html_e( 'Wallet', 'artly' ); ?></p>
      <h2 id="wallet-modal-title" class="artly-modal-title">
        <?php esc_html_e( 'Add points', 'artly' ); ?>
      </h2>
      <p class="artly-modal-subtitle">
        <?php esc_html_e( 'Choose a pack and continue to checkout. Points will be added to your wallet automatically after payment.', 'artly' ); ?>
      </p>
    </div>
    <div class="artly-modal-body">
      <?php if ( ! empty( $packs ) ) : ?>
        <div class="wallet-packs-grid">
          <?php foreach ( $packs as $pack ) : ?>
            <?php
            $name        = isset( $pack['name'] ) ? $pack['name'] : '';
            $points      = isset( $pack['points'] ) ? (float) $pack['points'] : 0.0;
            $price       = isset( $pack['price_label'] ) ? $pack['price_label'] : '';
            $highlight   = ! empty( $pack['highlight'] );
            $product_id  = isset( $pack['product_id'] ) ? (int) $pack['product_id'] : 0;
            $product_url = $product_id ? get_permalink( $product_id ) : '';
            $card_class  = $highlight ? 'wallet-pack-card wallet-pack-card--highlight' : 'wallet-pack-card';
            ?>
            <article class="<?php echo esc_attr( $card_class ); ?>">
              <div class="wallet-pack-header">
                <h3 class="wallet-pack-name"><?php echo esc_html( $name ); ?></h3>
                <?php if ( $highlight ) : ?>
                  <span class="wallet-pack-badge">
                    <?php esc_html_e( 'Best value', 'artly' ); ?>
                  </span>
                <?php endif; ?>
              </div>
              <div class="wallet-pack-points">
                <?php
                printf(
                    esc_html__( '%.0f points', 'artly' ),
                    $points
                );
                ?>
              </div>
              <?php if ( $price ) : ?>
                <div class="wallet-pack-price">
                  <?php echo esc_html( $price ); ?>
                </div>
              <?php endif; ?>
              <?php if ( ! empty( $pack['description'] ) ) : ?>
                <p class="wallet-pack-description">
                  <?php echo esc_html( $pack['description'] ); ?>
                </p>
              <?php endif; ?>
              <?php if ( $product_url ) : ?>
                <a class="wallet-pack-btn" href="<?php echo esc_url( $product_url ); ?>">
                  <?php esc_html_e( 'Continue to checkout', 'artly' ); ?>
                </a>
              <?php else : ?>
                <span class="wallet-pack-note">
                  <?php esc_html_e( 'This pack is not available yet.', 'artly' ); ?>
                </span>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else : ?>
        <p class="wallet-pack-empty">
          <?php esc_html_e( 'No top-up packs are available right now. Please contact support.', 'artly' ); ?>
        </p>
      <?php endif; ?>
    </div>
  </div>
</div>

