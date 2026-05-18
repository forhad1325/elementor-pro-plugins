<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EFLT_Form_Controls {

    public static function init() {
        add_action( 'elementor/element/form/section_form_options/after_section_end', [ __CLASS__, 'add_secure_access_section' ], 10, 2 );
    }

    public static function add_secure_access_section( $element, $args ) {

        $element->start_controls_section( 'eflt_secure_access_section', [
            'label' => __( '🔒 Secure Access Gate', 'elementor-form-lead-tracker' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        $element->add_control( 'eflt_enable_secure_access', [
            'label'        => __( 'Enable Secure Access', 'elementor-form-lead-tracker' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'label_on'     => __( 'Yes', 'elementor-form-lead-tracker' ),
            'label_off'    => __( 'No', 'elementor-form-lead-tracker' ),
            'return_value' => 'yes',
            'default'      => '',
            'description'  => __( 'When enabled, the download page will only be accessible to users who submit this form.', 'elementor-form-lead-tracker' ),
        ] );

        $element->add_control( 'eflt_email_field_id', [
            'label'       => __( 'Email Field ID', 'elementor-form-lead-tracker' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'default'     => 'email',
            'placeholder' => 'email',
            'description' => __( 'The ID of the email field in this form (check field Advanced tab).', 'elementor-form-lead-tracker' ),
            'condition'   => [ 'eflt_enable_secure_access' => 'yes' ],
        ] );

        $element->add_control( 'eflt_name_field_id', [
            'label'       => __( 'Name Field ID', 'elementor-form-lead-tracker' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'default'     => 'name',
            'placeholder' => 'name',
            'description' => __( 'The ID of the name field in this form.', 'elementor-form-lead-tracker' ),
            'condition'   => [ 'eflt_enable_secure_access' => 'yes' ],
        ] );

        $element->add_control( 'eflt_gated_page_id', [
            'label'       => __( 'Secure Download Page', 'elementor-form-lead-tracker' ),
            'type'        => \Elementor\Controls_Manager::SELECT2,
            'options'     => self::get_pages_list(),
            'default'     => '',
            'description' => __( 'Select the download page to protect. Only users who submit this form can access it.', 'elementor-form-lead-tracker' ),
            'condition'   => [ 'eflt_enable_secure_access' => 'yes' ],
        ] );

        $element->add_control( 'eflt_blocked_page_id', [
            'label'       => __( 'Access Denied Page', 'elementor-form-lead-tracker' ),
            'type'        => \Elementor\Controls_Manager::SELECT2,
            'options'     => self::get_pages_list(),
            'default'     => '',
            'description' => __( 'Page shown when someone visits without a valid session. Leave empty to redirect to homepage.', 'elementor-form-lead-tracker' ),
            'condition'   => [ 'eflt_enable_secure_access' => 'yes' ],
        ] );

        $element->add_control( 'eflt_cookie_days', [
            'label'       => __( 'Cookie Duration (days)', 'elementor-form-lead-tracker' ),
            'type'        => \Elementor\Controls_Manager::NUMBER,
            'default'     => 7,
            'min'         => 1,
            'max'         => 365,
            'description' => __( 'How long the access cookie remains valid.', 'elementor-form-lead-tracker' ),
            'condition'   => [ 'eflt_enable_secure_access' => 'yes' ],
        ] );

        $element->add_control( 'eflt_heading_tracking', [
            'label'     => __( 'Download Tracking', 'elementor-form-lead-tracker' ),
            'type'      => \Elementor\Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => [ 'eflt_enable_secure_access' => 'yes' ],
        ] );

        $element->add_control( 'eflt_track_downloads', [
            'label'        => __( 'Track Download Clicks', 'elementor-form-lead-tracker' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'label_on'     => __( 'Yes', 'elementor-form-lead-tracker' ),
            'label_off'    => __( 'No', 'elementor-form-lead-tracker' ),
            'return_value' => 'yes',
            'default'      => 'yes',
            'description'  => __( 'Record which buttons/PDFs each user clicks.', 'elementor-form-lead-tracker' ),
            'condition'    => [ 'eflt_enable_secure_access' => 'yes' ],
        ] );

        $element->add_control( 'eflt_heading_ga4', [
            'label'     => __( 'Google Analytics', 'elementor-form-lead-tracker' ),
            'type'      => \Elementor\Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => [ 'eflt_enable_secure_access' => 'yes' ],
        ] );

        $element->add_control( 'eflt_enable_ga4', [
            'label'        => __( 'Send Events to GA4', 'elementor-form-lead-tracker' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'label_on'     => __( 'Yes', 'elementor-form-lead-tracker' ),
            'label_off'    => __( 'No', 'elementor-form-lead-tracker' ),
            'return_value' => 'yes',
            'default'      => '',
            'description'  => __( 'Fire file_download events to GA4 when users click download buttons.', 'elementor-form-lead-tracker' ),
            'condition'    => [ 'eflt_enable_secure_access' => 'yes' ],
        ] );

        $element->add_control( 'eflt_heading_freshsales', [
            'label'     => __( 'Freshsales CRM', 'elementor-form-lead-tracker' ),
            'type'      => \Elementor\Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => [ 'eflt_enable_secure_access' => 'yes' ],
        ] );

        $element->add_control( 'eflt_enable_freshsales', [
            'label'        => __( 'Tag Downloads in Freshsales', 'elementor-form-lead-tracker' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'label_on'     => __( 'Yes', 'elementor-form-lead-tracker' ),
            'label_off'    => __( 'No', 'elementor-form-lead-tracker' ),
            'return_value' => 'yes',
            'default'      => '',
            'description'  => __( 'Add download tags and notes to the contact in Freshsales CRM.', 'elementor-form-lead-tracker' ),
            'condition'    => [ 'eflt_enable_secure_access' => 'yes' ],
        ] );

        $element->end_controls_section();
    }

    private static function get_pages_list() {
        $pages  = get_pages( [ 'post_status' => 'publish' ] );
        $result = [ '' => '— Select Page —' ];
        foreach ( $pages as $page ) {
            $result[ $page->ID ] = $page->post_title;
        }
        return $result;
    }

    public static function get_form_secure_settings( $form_settings ) {
        return [
            'enabled'            => ( $form_settings['eflt_enable_secure_access'] ?? '' ) === 'yes',
            'email_field_id'     => $form_settings['eflt_email_field_id'] ?? 'email',
            'name_field_id'      => $form_settings['eflt_name_field_id'] ?? 'name',
            'gated_page_id'      => absint( $form_settings['eflt_gated_page_id'] ?? 0 ),
            'access_denied_page' => absint( $form_settings['eflt_blocked_page_id'] ?? 0 ),
            'cookie_days'        => absint( $form_settings['eflt_cookie_days'] ?? 7 ),
            'track_downloads'    => ( $form_settings['eflt_track_downloads'] ?? 'yes' ) === 'yes',
            'enable_ga4'         => ( $form_settings['eflt_enable_ga4'] ?? '' ) === 'yes',
            'enable_freshsales'  => ( $form_settings['eflt_enable_freshsales'] ?? '' ) === 'yes',
        ];
    }
}
