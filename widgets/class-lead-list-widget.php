<?php
/**
 * Lead List Widget Class for Elementor
 *
 * @package Simple WP CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

/**
 * Lead List Widget Class.
 */
class Lead_List_Widget extends Widget_Base {

    /**
     * Get widget slug (name).
     */
    public function get_name() {
        return 'lead_list_widget';
    }

    /**
     * Get widget title (label shown inside Elementor).
     */
    public function get_title() {
        return esc_html__( 'Lead List Widget', 'simple-wp-crm' );
    }

    /**
     * Get widget icon (Elementor editor icon).
     */
    public function get_icon() {
        return 'eicon-post-list'; // Built-in Elementor icon.
    }

    /**
     * Get widget categories.
     */
    public function get_categories() {
        return [ 'general' ];
    }

/**
 * Register widget controls for Elementor editor.
 */
protected function register_controls() {
    $this->start_controls_section(
        'section_content',
        [
            'label' => esc_html__( 'Content Settings', 'simple-wp-crm' ),
        ]
    );

    // Lead Status Filter
    $this->add_control(
        'lead_status',
        [
            'label' => esc_html__( 'Filter by Status', 'simple-wp-crm' ),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => [
                '' => esc_html__( 'All', 'simple-wp-crm' ),
                'new' => esc_html__( 'New', 'simple-wp-crm' ),
                'contacted' => esc_html__( 'Contacted', 'simple-wp-crm' ),
                'won' => esc_html__( 'Won', 'simple-wp-crm' ),
                'lost' => esc_html__( 'Lost', 'simple-wp-crm' ),
            ],
            'default' => '',
        ]
    );

    // Lead Limit Control
    $this->add_control(
        'lead_limit',
        [
            'label' => esc_html__( 'Number of Leads to Show', 'simple-wp-crm' ),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => -1,
            'min' => -1,
            'description' => esc_html__( 'Set -1 for unlimited.', 'simple-wp-crm' ),
        ]
    );

    $this->end_controls_section();

    // Call Style Controls
    $this->start_controls_section(
        'section_style',
        [
            'label' => esc_html__( 'Style Settings', 'simple-wp-crm' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]
    );

    // Typography
    $this->add_group_control(
        \Elementor\Group_Control_Typography::get_type(),
        [
            'name'     => 'lead_typography',
            'selector' => '{{WRAPPER}} .lead-list-widget li',
        ]
    );

    // Text Color
    $this->add_control(
        'lead_text_color',
        [
            'label' => esc_html__( 'Text Color', 'simple-wp-crm' ),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lead-list-widget li' => 'color: {{VALUE}};',
            ],
        ]
    );

    // Background Color
    $this->add_control(
        'lead_bg_color',
        [
            'label' => esc_html__( 'Background Color', 'simple-wp-crm' ),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lead-list-widget' => 'background-color: {{VALUE}};',
            ],
        ]
    );

    // Padding
    $this->add_responsive_control(
        'lead_padding',
        [
            'label' => esc_html__( 'Padding', 'simple-wp-crm' ),
            'type'  => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', '%', 'em' ],
            'selectors' => [
                '{{WRAPPER}} .lead-list-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]
    );

    $this->end_controls_section();
}


/**
 * Render widget output on frontend.
 */
protected function render() {

    $settings = $this->get_settings_for_display();

    $args = [
        'post_type'      => 'lead',
        'post_status'    => 'publish',
        'posts_per_page' => ! empty( $settings['lead_limit'] ) ? intval( $settings['lead_limit'] ) : -1,
    ];

    if ( ! empty( $settings['lead_status'] ) ) {
        $args['meta_query'] = [
            [
                'key'     => '_swc_status',
                'value'   => sanitize_text_field( $settings['lead_status'] ),
                'compare' => '=',
            ],
        ];
    }

    $leads_query = new WP_Query( $args );

    if ( $leads_query->have_posts() ) {
        echo '<div class="lead-list-widget">';
        echo '<ul>';

        while ( $leads_query->have_posts() ) {
            $leads_query->the_post();

            $lead_id    = get_the_ID();
            $lead_title = get_the_title( $lead_id );
            $email      = get_post_meta( $lead_id, '_swc_email', true );
            $phone      = get_post_meta( $lead_id, '_swc_phone', true );
            $status     = get_post_meta( $lead_id, '_swc_status', true );

            echo '<li style="margin-bottom:15px;">';
            echo '<strong>' . esc_html( $lead_title ) . '</strong><br>';
            echo esc_html__( 'Email: ', 'simple-wp-crm' ) . esc_html( $email ) . '<br>';
            echo esc_html__( 'Phone: ', 'simple-wp-crm' ) . esc_html( $phone ) . '<br>';
            echo esc_html__( 'Status: ', 'simple-wp-crm' ) . esc_html( ucfirst( $status ) );
            echo '</li>';
        }

        echo '</ul>';
        echo '</div>';

        wp_reset_postdata();
    } else {
        echo '<p>' . esc_html__( 'No leads found.', 'simple-wp-crm' ) . '</p>';
    }
}


}