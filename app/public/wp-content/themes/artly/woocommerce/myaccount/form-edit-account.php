<?php
/**
 * Edit account form – Artly themed (rebuild from scratch)
 *
 * Template path:
 * wp-content/themes/artly/woocommerce/myaccount/form-edit-account.php
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_edit_account_form' );

// Build success message from WooCommerce notices
$account_message = '';
$success_notices = wc_get_notices( 'success' );
if ( ! empty( $success_notices ) ) {
    $first_notice = reset( $success_notices );
    if ( isset( $first_notice['notice'] ) ) {
        $account_message = wp_strip_all_tags( wp_kses_post( $first_notice['notice'] ) );
    }
}

$user  = wp_get_current_user();
$phone = get_user_meta( $user->ID, 'billing_phone', true );
?>

<section class="artly-edit-account-page">
    <div class="artly-edit-account-inner">
        
        <!-- Hero Section -->
        <header class="artly-edit-account-hero">
            <p class="artly-edit-account-kicker">
                <?php esc_html_e( 'Account Settings', 'artly' ); ?>
            </p>
            <h1 class="artly-edit-account-title">
                <?php esc_html_e( 'Edit Your Profile', 'artly' ); ?>
            </h1>
            <p class="artly-edit-account-subtitle">
                <?php esc_html_e( 'Update your personal information, contact details, and security settings.', 'artly' ); ?>
            </p>
            
            <?php if ( ! empty( $account_message ) ) : ?>
                <div class="artly-edit-account-message">
                    <span class="artly-edit-account-message-dot" aria-hidden="true"></span>
                    <?php echo esc_html( $account_message ); ?>
                </div>
            <?php endif; ?>
        </header>

        <?php wc_print_notices(); ?>

        <form
            class="woocommerce-EditAccountForm artly-edit-account-form"
            action=""
            method="post"
            <?php do_action( 'woocommerce_edit_account_form_tag' ); ?>
        >
            <?php do_action( 'woocommerce_edit_account_form_start' ); ?>

            <div class="artly-edit-account-grid">
                
                <!-- Personal Information Card -->
                <div class="artly-edit-account-card artly-edit-account-card--personal">
                    <div class="artly-edit-account-card-header">
                        <div class="artly-edit-account-card-icon" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 12C14.7614 12 17 9.76142 17 7C17 4.23858 14.7614 2 12 2C9.23858 2 7 4.23858 7 7C7 9.76142 9.23858 12 12 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M4 22C4 18.134 7.13401 15 11 15H13C16.866 15 20 18.134 20 22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div class="artly-edit-account-card-title-group">
                            <h2 class="artly-edit-account-card-title"><?php esc_html_e( 'Personal Information', 'artly' ); ?></h2>
                            <p class="artly-edit-account-card-description"><?php esc_html_e( 'Your name and how it appears in your account.', 'artly' ); ?></p>
                        </div>
                    </div>
                    
                    <div class="artly-edit-account-card-body">
                        <div class="artly-edit-account-field-row">
                            <div class="artly-edit-account-field">
                                <label for="account_first_name" class="artly-edit-account-label">
                                    <?php esc_html_e( 'First name', 'woocommerce' ); ?>
                                    <span class="artly-required" aria-label="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="account_first_name"
                                    id="account_first_name"
                                    class="artly-edit-account-input"
                                    value="<?php echo esc_attr( $user->first_name ); ?>"
                                    autocomplete="given-name"
                                    aria-required="true"
                                    required
                                />
                            </div>
                            
                            <div class="artly-edit-account-field">
                                <label for="account_last_name" class="artly-edit-account-label">
                                    <?php esc_html_e( 'Last name', 'woocommerce' ); ?>
                                    <span class="artly-required" aria-label="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="account_last_name"
                                    id="account_last_name"
                                    class="artly-edit-account-input"
                                    value="<?php echo esc_attr( $user->last_name ); ?>"
                                    autocomplete="family-name"
                                    aria-required="true"
                                    required
                                />
                            </div>
                        </div>
                        
                        <div class="artly-edit-account-field">
                            <label for="account_display_name" class="artly-edit-account-label">
                                <?php esc_html_e( 'Display name', 'woocommerce' ); ?>
                                <span class="artly-required" aria-label="required">*</span>
                            </label>
                            <input
                                type="text"
                                name="account_display_name"
                                id="account_display_name"
                                class="artly-edit-account-input"
                                value="<?php echo esc_attr( $user->display_name ); ?>"
                                aria-required="true"
                                aria-describedby="account_display_name_description"
                                required
                            />
                            <p class="artly-edit-account-field-hint" id="account_display_name_description">
                                <?php esc_html_e( 'This will be how your name will be displayed in the account section and in reviews.', 'woocommerce' ); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Contact Information Card -->
                <div class="artly-edit-account-card artly-edit-account-card--contact">
                    <div class="artly-edit-account-card-header">
                        <div class="artly-edit-account-card-icon" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 4H20C21.1 4 22 4.9 22 6V18C22 19.1 21.1 20 20 20H4C2.9 20 2 19.1 2 18V6C2 4.9 2.9 4 4 4Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M22 6L12 13L2 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div class="artly-edit-account-card-title-group">
                            <h2 class="artly-edit-account-card-title"><?php esc_html_e( 'Contact Information', 'artly' ); ?></h2>
                            <p class="artly-edit-account-card-description"><?php esc_html_e( 'Update your email and phone number.', 'artly' ); ?></p>
                        </div>
                    </div>
                    
                    <div class="artly-edit-account-card-body">
                        <div class="artly-edit-account-field">
                            <label for="account_email" class="artly-edit-account-label">
                                <?php esc_html_e( 'Email address', 'woocommerce' ); ?>
                                <span class="artly-required" aria-label="required">*</span>
                            </label>
                            <input
                                type="email"
                                name="account_email"
                                id="account_email"
                                class="artly-edit-account-input"
                                value="<?php echo esc_attr( $user->user_email ); ?>"
                                autocomplete="email"
                                aria-required="true"
                                required
                            />
                        </div>
                        
                        <div class="artly-edit-account-field">
                            <label for="account_phone" class="artly-edit-account-label">
                                <?php esc_html_e( 'Phone number', 'artly' ); ?>
                                <span class="artly-edit-account-label-optional"><?php esc_html_e( '(optional)', 'artly' ); ?></span>
                            </label>
                            <input
                                type="tel"
                                name="account_phone"
                                id="account_phone"
                                class="artly-edit-account-input"
                                value="<?php echo esc_attr( $phone ); ?>"
                                autocomplete="tel"
                            />
                            <p class="artly-edit-account-field-hint">
                                <?php esc_html_e( 'We may use this for important account or billing alerts only.', 'artly' ); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Security Card -->
                <div class="artly-edit-account-card artly-edit-account-card--security">
                    <div class="artly-edit-account-card-header">
                        <div class="artly-edit-account-card-icon" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="4" y="11" width="16" height="9" rx="2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M8 11V8C8 5.79 9.79 4 12 4C14.21 4 16 5.79 16 8V11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="12" cy="15" r="1.5" fill="currentColor"/>
                            </svg>
                        </div>
                        <div class="artly-edit-account-card-title-group">
                            <h2 class="artly-edit-account-card-title"><?php esc_html_e( 'Security', 'artly' ); ?></h2>
                            <p class="artly-edit-account-card-description"><?php esc_html_e( 'Change your password to keep your account secure.', 'artly' ); ?></p>
                        </div>
                    </div>
                    
                    <div class="artly-edit-account-card-body">
                        <div class="artly-edit-account-field">
                            <label for="password_current" class="artly-edit-account-label">
                                <?php esc_html_e( 'Current password', 'woocommerce' ); ?>
                                <span class="artly-edit-account-label-optional"><?php esc_html_e( '(leave blank to keep current)', 'artly' ); ?></span>
                            </label>
                            <div class="artly-edit-account-password-wrapper">
                                <input
                                    type="password"
                                    name="password_current"
                                    id="password_current"
                                    class="artly-edit-account-input artly-edit-account-input--password"
                                    autocomplete="current-password"
                                />
                                <button
                                    type="button"
                                    class="artly-password-toggle"
                                    aria-label="<?php esc_attr_e( 'Show password', 'artly' ); ?>"
                                    data-target="password_current"
                                >
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
                        
                        <div class="artly-edit-account-field-row">
                            <div class="artly-edit-account-field">
                                <label for="password_1" class="artly-edit-account-label">
                                    <?php esc_html_e( 'New password', 'woocommerce' ); ?>
                                </label>
                                <div class="artly-edit-account-password-wrapper">
                                    <input
                                        type="password"
                                        name="password_1"
                                        id="password_1"
                                        class="artly-edit-account-input artly-edit-account-input--password"
                                        autocomplete="new-password"
                                        minlength="12"
                                    />
                                    <button
                                        type="button"
                                        class="artly-password-toggle"
                                        aria-label="<?php esc_attr_e( 'Show password', 'artly' ); ?>"
                                        data-target="password_1"
                                    >
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
                            
                            <div class="artly-edit-account-field">
                                <label for="password_2" class="artly-edit-account-label">
                                    <?php esc_html_e( 'Confirm new password', 'woocommerce' ); ?>
                                </label>
                                <div class="artly-edit-account-password-wrapper">
                                    <input
                                        type="password"
                                        name="password_2"
                                        id="password_2"
                                        class="artly-edit-account-input artly-edit-account-input--password"
                                        autocomplete="new-password"
                                        minlength="12"
                                    />
                                    <button
                                        type="button"
                                        class="artly-password-toggle"
                                        aria-label="<?php esc_attr_e( 'Show password', 'artly' ); ?>"
                                        data-target="password_2"
                                    >
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
                        </div>
                        
                        <p class="artly-edit-account-field-hint">
                            <?php esc_html_e( 'Password must be at least 12 characters and include uppercase, lowercase, numbers, and symbols.', 'artly' ); ?>
                        </p>
                    </div>
                </div>
            </div>

            <?php
            /**
             * Hook: woocommerce_edit_account_form.
             *
             * @since 2.6.0
             */
            do_action( 'woocommerce_edit_account_form' );
            ?>

            <div class="artly-edit-account-actions">
                <?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
                <button
                    type="submit"
                    class="artly-edit-account-save-btn"
                    name="save_account_details"
                    value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>"
                >
                    <span class="artly-edit-account-save-btn-text"><?php esc_html_e( 'Save changes', 'woocommerce' ); ?></span>
                    <svg class="artly-edit-account-save-btn-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M16.667 5L7.5 14.167L3.333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <input type="hidden" name="action" value="save_account_details" />
            </div>

            <?php do_action( 'woocommerce_edit_account_form_end' ); ?>
        </form>
    </div>
</section>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>
