<?php
/**
 * Edit account form (Artly override)
 * Minimal override - keeps WooCommerce logic intact, adds Artly styling classes
 *
 * @package Artly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hook - woocommerce_before_edit_account_form.
 *
 * @since 2.6.0
 */
do_action( 'woocommerce_before_edit_account_form' );
?>

<section class="artly-shell">
    <header class="artly-pagehead">
        <h2><?php esc_html_e( 'Profile Settings', 'artly' ); ?></h2>
        <p class="muted"><?php esc_html_e( 'Update your account information and security settings', 'artly' ); ?></p>
    </header>

    <form class="woocommerce-EditAccountForm edit-account artly-edit-account-form" action="" method="post" <?php do_action( 'woocommerce_edit_account_form_tag' ); ?>>
        <?php do_action( 'woocommerce_edit_account_form_start' ); ?>

        <div class="artly-edit-account-grid">
            <!-- Personal Information Card -->
            <div class="card glass artly-account-section">
                <div class="artly-section-header">
                    <h3><?php esc_html_e( 'Personal Information', 'artly' ); ?></h3>
                </div>
                <div class="artly-section-body">
                    <div class="artly-form-row artly-form-row--split">
                        <p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
                            <label for="account_first_name"><?php esc_html_e( 'First name', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_first_name" id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr( $user->first_name ); ?>" aria-required="true" />
                        </p>
                        <p class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
                            <label for="account_last_name"><?php esc_html_e( 'Last name', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_last_name" id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr( $user->last_name ); ?>" aria-required="true" />
                        </p>
                    </div>
                    <div class="clear"></div>

                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                        <label for="account_display_name"><?php esc_html_e( 'Display name', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_display_name" id="account_display_name" aria-describedby="account_display_name_description" value="<?php echo esc_attr( $user->display_name ); ?>" aria-required="true" />
                        <span id="account_display_name_description" class="artly-field-hint"><em><?php esc_html_e( 'This will be how your name will be displayed in the account section and in reviews', 'woocommerce' ); ?></em></span>
                    </p>
                </div>
            </div>

            <!-- Contact Information Card -->
            <div class="card glass artly-account-section">
                <div class="artly-section-header">
                    <h3><?php esc_html_e( 'Contact Information', 'artly' ); ?></h3>
                </div>
                <div class="artly-section-body">
                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                        <label for="account_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                        <input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr( $user->user_email ); ?>" aria-required="true" />
                    </p>

                    <?php
                    /**
                     * Hook where additional fields should be rendered.
                     *
                     * @since 8.7.0
                     */
                    do_action( 'woocommerce_edit_account_form_fields' );
                    ?>
                </div>
            </div>

            <!-- Security Card -->
            <div class="card glass artly-account-section artly-password-section">
                <div class="artly-section-header">
                    <h3><?php esc_html_e( 'Security', 'artly' ); ?></h3>
                </div>
                <div class="artly-section-body">
                    <fieldset class="artly-password-fieldset">
                        <legend class="artly-password-legend"><?php esc_html_e( 'Password change', 'woocommerce' ); ?></legend>
                        <p class="artly-password-hint"><?php esc_html_e( 'Leave blank to keep your current password', 'artly' ); ?></p>

                        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                            <label for="password_current"><?php esc_html_e( 'Current password', 'woocommerce' ); ?></label>
                            <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current" autocomplete="off" />
                        </p>
                        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                            <label for="password_1"><?php esc_html_e( 'New password', 'woocommerce' ); ?></label>
                            <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1" autocomplete="off" />
                        </p>
                        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                            <label for="password_2"><?php esc_html_e( 'Confirm new password', 'woocommerce' ); ?></label>
                            <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_2" id="password_2" autocomplete="off" />
                        </p>
                    </fieldset>
                </div>
            </div>
        </div>

        <div class="clear"></div>

        <?php
        /**
         * My Account edit account form.
         *
         * @since 2.6.0
         */
        do_action( 'woocommerce_edit_account_form' );
        ?>

        <div class="artly-form-actions">
            <?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
            <button type="submit" class="woocommerce-Button button btn primary artly-btn-save<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="save_account_details" value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>">
                <?php esc_html_e( 'Save changes', 'woocommerce' ); ?>
            </button>
            <input type="hidden" name="action" value="save_account_details" />
        </div>

        <?php do_action( 'woocommerce_edit_account_form_end' ); ?>
    </form>
</section>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>

