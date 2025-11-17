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

$account_message = '';

if ( function_exists( 'wc_get_notices' ) ) {
    $success_notices = wc_get_notices( 'success' );

    if ( ! empty( $success_notices ) ) {
        $first_notice = reset( $success_notices );

        if ( isset( $first_notice['notice'] ) ) {
            $account_message = wp_strip_all_tags( wp_kses_post( $first_notice['notice'] ) );
        }
    }
}
?>

<main class="artly-account-page">
    <div class="artly-container artly-account-container">
        <header class="artly-account-header">
            <h1 class="artly-account-title"><?php esc_html_e( 'Account settings', 'artly' ); ?></h1>
            <p class="artly-account-subtitle"><?php esc_html_e( 'Manage your personal information, contact details, and security preferences.', 'artly' ); ?></p>
            <?php if ( ! empty( $account_message ) ) : ?>
                <p class="artly-account-message"><span class="artly-account-status-dot" aria-hidden="true"></span><?php echo esc_html( $account_message ); ?></p>
            <?php endif; ?>
        </header>

        <form class="woocommerce-EditAccountForm edit-account artly-account-form" action="" method="post" <?php do_action( 'woocommerce_edit_account_form_tag' ); ?>>
            <?php do_action( 'woocommerce_edit_account_form_start' ); ?>

            <div class="artly-account-grid">
                <section class="artly-account-card artly-account-card--personal">
                    <div class="artly-account-card-header">
                        <div class="artly-account-icon-badge">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M12 12C14.7614 12 17 9.76142 17 7C17 4.23858 14.7614 2 12 2C9.23858 2 7 4.23858 7 7C7 9.76142 9.23858 12 12 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M20.59 22C20.59 18.13 16.74 15 12 15C7.26 15 3.41 18.13 3.41 22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div class="artly-account-card-heading">
                            <h2><?php esc_html_e( 'Personal info', 'artly' ); ?></h2>
                            <p><?php esc_html_e( 'Your name and how it appears in your account.', 'artly' ); ?></p>
                        </div>
                    </div>

                    <div class="artly-account-card-body">
                        <div class="artly-account-field-row">
                            <div class="artly-account-field">
                                <label for="account_first_name">
                                    <?php esc_html_e( 'First name', 'woocommerce' ); ?>
                                    <span class="artly-required">*</span>
                                </label>
                                <input type="text" name="account_first_name" id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr( $user->first_name ); ?>" aria-required="true" />
                            </div>
                            <div class="artly-account-field">
                                <label for="account_last_name">
                                    <?php esc_html_e( 'Last name', 'woocommerce' ); ?>
                                    <span class="artly-required">*</span>
                                </label>
                                <input type="text" name="account_last_name" id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr( $user->last_name ); ?>" aria-required="true" />
                            </div>
                        </div>

                        <div class="artly-account-field">
                            <label for="account_display_name">
                                <?php esc_html_e( 'Display name', 'woocommerce' ); ?>
                                <span class="artly-required">*</span>
                            </label>
                            <input type="text" name="account_display_name" id="account_display_name" aria-describedby="account_display_name_description" value="<?php echo esc_attr( $user->display_name ); ?>" aria-required="true" />
                            <span id="account_display_name_description" class="artly-account-field-hint"><?php esc_html_e( 'This will be how your name will be displayed in the account section and in reviews', 'woocommerce' ); ?></span>
                        </div>
                    </div>
                </section>

                <section class="artly-account-card artly-account-card--contact">
                    <div class="artly-account-card-header">
                        <div class="artly-account-icon-badge">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M4 4H20C21.1 4 22 4.9 22 6V18C22 19.1 21.1 20 20 20H4C2.9 20 2 19.1 2 18V6C2 4.9 2.9 4 4 4Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <polyline points="22,6 12,13 2,6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div class="artly-account-card-heading">
                            <h2><?php esc_html_e( 'Contact info', 'artly' ); ?></h2>
                            <p><?php esc_html_e( 'Update the email address we use to contact you.', 'artly' ); ?></p>
                        </div>
                    </div>

                    <div class="artly-account-card-body">
                        <div class="artly-account-field">
                            <label for="account_email">
                                <?php esc_html_e( 'Email address', 'woocommerce' ); ?>
                                <span class="artly-required">*</span>
                            </label>
                            <input type="email" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr( $user->user_email ); ?>" aria-required="true" />
                        </div>

                        <?php
                        /**
                         * Hook where additional fields should be rendered.
                         *
                         * @since 8.7.0
                         */
                        do_action( 'woocommerce_edit_account_form_fields' );
                        ?>
                    </div>
                </section>

                <section class="artly-account-card artly-account-card--security">
                    <div class="artly-account-card-header">
                        <div class="artly-account-icon-badge">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M7 11V7C7 5.67392 7.52678 4.40215 8.46447 3.46447C9.40215 2.52678 10.6739 2 12 2C13.3261 2 14.5979 2.52678 15.5355 3.46447C16.4732 4.40215 17 5.67392 17 7V11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div class="artly-account-card-heading">
                            <h2><?php esc_html_e( 'Password & security', 'artly' ); ?></h2>
                            <p><?php esc_html_e( 'Change your password and keep your account secure.', 'artly' ); ?></p>
                        </div>
                    </div>

                    <div class="artly-account-card-body">
                        <div class="artly-account-field">
                            <label for="password_current"><?php esc_html_e( 'Current password', 'woocommerce' ); ?></label>
                            <div class="artly-account-input-wrapper">
                                <input type="password" name="password_current" id="password_current" autocomplete="off" />
                                <button type="button" class="artly-password-toggle" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'artly' ); ?>" data-target="password_current">
                                    <svg class="artly-icon-eye" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M10 4C6 4 2.73 6.11 1 9.5C2.73 12.89 6 15 10 15C14 15 17.27 12.89 19 9.5C17.27 6.11 14 4 10 4Z" stroke="currentColor" stroke-width="1.5"/>
                                        <circle cx="10" cy="9.5" r="2.5" stroke="currentColor" stroke-width="1.5"/>
                                    </svg>
                                    <svg class="artly-icon-eye-off" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: none;">
                                        <path d="M1 1L19 19M8.88 8.88C8.3 9.46 8 10.2 8 11C8 12.66 9.34 14 11 14C11.8 14 12.54 13.7 13.12 13.12M14.71 11.71C14.89 11.27 15 10.65 15 10C15 7.79 13.21 6 11 6C10.35 6 9.73 6.11 9.29 6.29M3.41 4.59C2.73 5.27 2.15 6.05 1.68 6.91C0.44 9.11 0.44 10.89 1.68 13.09C2.73 15.07 5.41 18 10 18C11.05 18 12.03 17.83 12.91 17.53" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="artly-account-field">
                            <label for="password_1"><?php esc_html_e( 'New password', 'woocommerce' ); ?></label>
                            <div class="artly-account-input-wrapper">
                                <input type="password" name="password_1" id="password_1" autocomplete="off" />
                                <button type="button" class="artly-password-toggle" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'artly' ); ?>" data-target="password_1">
                                    <svg class="artly-icon-eye" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M10 4C6 4 2.73 6.11 1 9.5C2.73 12.89 6 15 10 15C14 15 17.27 12.89 19 9.5C17.27 6.11 14 4 10 4Z" stroke="currentColor" stroke-width="1.5"/>
                                        <circle cx="10" cy="9.5" r="2.5" stroke="currentColor" stroke-width="1.5"/>
                                    </svg>
                                    <svg class="artly-icon-eye-off" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: none;">
                                        <path d="M1 1L19 19M8.88 8.88C8.3 9.46 8 10.2 8 11C8 12.66 9.34 14 11 14C11.8 14 12.54 13.7 13.12 13.12M14.71 11.71C14.89 11.27 15 10.65 15 10C15 7.79 13.21 6 11 6C10.35 6 9.73 6.11 9.29 6.29M3.41 4.59C2.73 5.27 2.15 6.05 1.68 6.91C0.44 9.11 0.44 10.89 1.68 13.09C2.73 15.07 5.41 18 10 18C11.05 18 12.03 17.83 12.91 17.53" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="artly-account-field">
                            <label for="password_2"><?php esc_html_e( 'Confirm new password', 'woocommerce' ); ?></label>
                            <div class="artly-account-input-wrapper">
                                <input type="password" name="password_2" id="password_2" autocomplete="off" />
                                <button type="button" class="artly-password-toggle" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'artly' ); ?>" data-target="password_2">
                                    <svg class="artly-icon-eye" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M10 4C6 4 2.73 6.11 1 9.5C2.73 12.89 6 15 10 15C14 15 17.27 12.89 19 9.5C17.27 6.11 14 4 10 4Z" stroke="currentColor" stroke-width="1.5"/>
                                        <circle cx="10" cy="9.5" r="2.5" stroke="currentColor" stroke-width="1.5"/>
                                    </svg>
                                    <svg class="artly-icon-eye-off" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: none;">
                                        <path d="M1 1L19 19M8.88 8.88C8.3 9.46 8 10.2 8 11C8 12.66 9.34 14 11 14C11.8 14 12.54 13.7 13.12 13.12M14.71 11.71C14.89 11.27 15 10.65 15 10C15 7.79 13.21 6 11 6C10.35 6 9.73 6.11 9.29 6.29M3.41 4.59C2.73 5.27 2.15 6.05 1.68 6.91C0.44 9.11 0.44 10.89 1.68 13.09C2.73 15.07 5.41 18 10 18C11.05 18 12.03 17.83 12.91 17.53" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <p class="artly-password-hint"><?php esc_html_e( 'Password must be at least 12 characters and include uppercase, lowercase, numbers, and symbols.', 'artly' ); ?></p>
                    </div>
                </section>
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

            <div class="artly-account-actions">
                <?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
                <button type="submit" class="artly-btn artly-btn-primary artly-account-save" name="save_account_details" value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>">
                    <?php esc_html_e( 'Save changes', 'woocommerce' ); ?>
                    <span class="artly-btn-arrow" aria-hidden="true">✔</span>
                </button>
                <input type="hidden" name="action" value="save_account_details" />
            </div>

            <?php do_action( 'woocommerce_edit_account_form_end' ); ?>
        </form>
    </div>
</main>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>

