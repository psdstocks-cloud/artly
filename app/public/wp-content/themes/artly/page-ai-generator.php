<?php
/**
 * Template Name: Artly – AI Generator
 * Description: AI image generation workspace using Nehtw-powered Artly endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_user_logged_in() ) {
    $redirect_to = get_permalink();
    $login_url   = wp_login_url( $redirect_to );
    wp_safe_redirect( $login_url );
    exit;
}

get_header();

$user_id = get_current_user_id();

$points_balance = function_exists( 'nehtw_gateway_get_user_points_balance' )
    ? intval( nehtw_gateway_get_user_points_balance( $user_id ) )
    : 0;

// Use the correct option names that match the admin settings
$ai_cost_generate = (int) get_option( 'artly_ai_generate_cost_points', 10 );
$ai_cost_vary     = (int) get_option( 'artly_ai_vary_cost_points', 6 );
$ai_cost_upscale  = (int) get_option( 'artly_ai_upscale_cost_points', 4 );
?>

<main id="primary" class="site-main artly-ai-generator-page">
  <div class="artly-dashboard-inner">

    <section class="artly-ai-top">
      <div class="artly-ai-top-text">
        <p class="artly-ai-kicker"><?php esc_html_e( 'AI Studio', 'artly' ); ?></p>
        <h1 class="artly-ai-title"><?php esc_html_e( 'AI Image Generator', 'artly' ); ?></h1>
        <p class="artly-ai-subtitle">
          <?php esc_html_e( 'Turn your ideas into ready-to-use visuals for your projects.', 'artly' ); ?>
        </p>
      </div>

      <div class="artly-card artly-ai-balance-card" aria-live="polite">
        <div class="artly-ai-balance-row">
          <div class="artly-ai-balance-label"><?php esc_html_e( 'Your balance', 'artly' ); ?></div>
          <div class="artly-ai-balance-value">
            <span id="artly-ai-balance-amount"><?php echo esc_html( number_format_i18n( $points_balance, 0 ) ); ?></span>
            <span class="artly-ai-balance-unit"><?php esc_html_e( 'pts', 'artly' ); ?></span>
          </div>
        </div>
        <p class="artly-ai-cost-line">
          <?php
          printf(
              /* translators: 1: generation cost, 2: vary cost, 3: upscale cost */
              esc_html__( 'Generate: %1$d pts • Vary: %2$d pts • Upscale: %3$d pts', 'artly' ),
              intval( $ai_cost_generate ),
              intval( $ai_cost_vary ),
              intval( $ai_cost_upscale )
          );
          ?>
        </p>
        <a class="artly-btn artly-btn-ghost artly-ai-balance-link" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">
          <?php esc_html_e( 'Buy more points', 'artly' ); ?>
        </a>
      </div>
    </section>

    <section class="artly-ai-layout">
      <div class="artly-ai-left">
        <div class="artly-card artly-ai-card">
          <label class="artly-ai-label" for="artly-ai-prompt"><?php esc_html_e( 'Prompt', 'artly' ); ?></label>
          <textarea id="artly-ai-prompt" name="artly-ai-prompt" rows="8" maxlength="500" placeholder="<?php esc_attr_e( 'Describe what you want to create…', 'artly' ); ?>"></textarea>
          <div class="artly-ai-meta-row">
            <span class="artly-ai-counter" id="artly-ai-counter">0 / 300</span>
            <span class="artly-ai-help"><?php esc_html_e( 'Tip: Include style, lighting, and mood for better results.', 'artly' ); ?></span>
          </div>

          <div class="artly-ai-chips" aria-label="<?php esc_attr_e( 'Prompt presets', 'artly' ); ?>">
            <?php
            $presets = array(
                __( 'Product mockup', 'artly' ),
                __( 'Social media post', 'artly' ),
                __( 'Flat illustration', 'artly' ),
                __( 'Realistic portrait', 'artly' ),
                __( 'Minimalist icon', 'artly' ),
            );

            foreach ( $presets as $preset ) :
                ?>
                <button type="button" class="artly-ai-chip" data-preset="<?php echo esc_attr( $preset ); ?>"><?php echo esc_html( $preset ); ?></button>
              <?php endforeach; ?>
          </div>

          <div class="artly-ai-controls">
            <div class="artly-ai-control">
              <label for="artly-ai-style" class="artly-ai-label--small"><?php esc_html_e( 'Style', 'artly' ); ?></label>
              <select id="artly-ai-style" name="artly-ai-style">
                <option value="default"><?php esc_html_e( 'Default', 'artly' ); ?></option>
                <option value="photo"><?php esc_html_e( 'Photo', 'artly' ); ?></option>
                <option value="illustration"><?php esc_html_e( 'Illustration', 'artly' ); ?></option>
                <option value="3d"><?php esc_html_e( '3D Render', 'artly' ); ?></option>
              </select>
            </div>
            <div class="artly-ai-control-note"><?php esc_html_e( 'We generate up to 4 images per prompt.', 'artly' ); ?></div>
          </div>

          <div class="artly-ai-actions">
            <button id="artly-ai-generate-btn" class="artly-btn artly-btn-primary" type="button">
              <?php esc_html_e( 'Generate Images', 'artly' ); ?>
            </button>
            <p class="artly-ai-cost-note">
              <?php
              printf(
                  /* translators: %d: points cost for a generation */
                  esc_html__( 'This will use %d points from your balance.', 'artly' ),
                  intval( $ai_cost_generate )
              );
              ?>
            </p>
          </div>
        </div>
      </div>

      <div class="artly-ai-right">
        <div class="artly-card artly-ai-state" id="artly-ai-state">

          <div id="artly-ai-empty-state" class="artly-ai-state-block">
            <div class="artly-ai-illustration" aria-hidden="true"></div>
            <h2><?php esc_html_e( 'Start by writing a prompt', 'artly' ); ?></h2>
            <p><?php esc_html_e( 'Describe what you want, then click Generate to see AI-created images.', 'artly' ); ?></p>
          </div>

          <div id="artly-ai-processing-state" class="artly-ai-state-block" hidden>
            <div class="artly-ai-processing-header">
              <span class="artly-ai-chip artly-ai-chip--status"><?php esc_html_e( 'Processing', 'artly' ); ?></span>
              <h2><?php esc_html_e( 'Generating your images…', 'artly' ); ?></h2>
              <p><?php esc_html_e( 'This usually takes a few seconds.', 'artly' ); ?></p>
            </div>
            <div class="artly-ai-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
              <div class="artly-ai-progress-fill" id="artly-ai-progress-fill"></div>
            </div>
          </div>

          <div id="artly-ai-result-state" class="artly-ai-state-block" hidden>
            <div class="artly-ai-state-header">
              <h2><?php esc_html_e( 'Your images are ready', 'artly' ); ?></h2>
              <p><?php esc_html_e( 'Click any image to download, vary, or upscale.', 'artly' ); ?></p>
            </div>
            <div class="artly-ai-result-grid" id="artly-ai-result-grid"></div>
          </div>

          <div id="artly-ai-nopoints-state" class="artly-ai-state-block" hidden>
            <h2><?php esc_html_e( 'Not enough points', 'artly' ); ?></h2>
            <p>
              <?php
              printf(
                  /* translators: 1: required points, 2: current points */
                  esc_html__( 'You need %1$d points for this action but your balance is %2$d points.', 'artly' ),
                  intval( $ai_cost_generate ),
                  intval( $points_balance )
              );
              ?>
            </p>
            <p class="artly-ai-nopoints-dynamic" id="artly-ai-nopoints-dynamic"></p>
            <a class="artly-btn artly-btn-primary" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">
              <?php esc_html_e( 'View points packages', 'artly' ); ?>
            </a>
          </div>

          <div id="artly-ai-error-state" class="artly-ai-state-block" hidden>
            <h2><?php esc_html_e( 'Something went wrong', 'artly' ); ?></h2>
            <p id="artly-ai-error-message"></p>
            <button type="button" class="artly-btn artly-btn-ghost" id="artly-ai-error-retry">
              <?php esc_html_e( 'Try again', 'artly' ); ?>
            </button>
          </div>

        </div>
      </div>
    </section>

  </div>
</main>

<?php
get_footer();