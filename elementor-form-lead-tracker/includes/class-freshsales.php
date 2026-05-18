<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EFLT_Freshsales {

    public static function sync_contact( $email, $name = '', $phone = '', $company = '', $form_name = '' ) {
        $domain  = get_option( 'eflt_freshsales_domain', '' );
        $api_key = get_option( 'eflt_freshsales_api_key', '' );
        if ( empty( $domain ) || empty( $api_key ) ) return;

        $contact_data = [ 'email' => $email ];
        if ( ! empty( $name ) ) {
            $parts = explode( ' ', trim( $name ), 2 );
            $contact_data['first_name'] = $parts[0];
            if ( ! empty( $parts[1] ) ) $contact_data['last_name'] = $parts[1];
        }
        if ( ! empty( $phone ) )   $contact_data['mobile_number'] = $phone;
        if ( ! empty( $company ) ) $contact_data['company'] = $company;

        $url      = 'https://' . $domain . '/crm/sales/api/contacts/upsert';
        $response = wp_remote_post( $url, [
            'headers' => self::get_headers( $api_key ),
            'body'    => wp_json_encode( [
                'contact'           => $contact_data,
                'unique_identifier' => [ 'emails' => $email ],
            ] ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( 'EFLT Freshsales sync_contact error: ' . $response->get_error_message() );
            return;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! in_array( $code, [ 200, 201 ], true ) || empty( $body['contact'] ) ) {
            error_log( 'EFLT Freshsales sync_contact failed (HTTP ' . $code . '): ' . wp_remote_retrieve_body( $response ) );
            return;
        }

        $contact       = $body['contact'];
        $contact_id    = $contact['id'];
        $existing_tags = $contact['tags'] ?? [];
        $new_tags      = [ 'Form Lead' ];
        if ( ! empty( $form_name ) ) $new_tags[] = 'Form: ' . $form_name;
        $merged = array_unique( array_merge( $existing_tags, $new_tags ) );
        if ( $merged !== $existing_tags ) {
            self::update_contact_tags( $contact_id, $merged, $domain, $api_key );
        }
    }

    public static function tag_download( $email, $pdf_label, $file_url ) {
        $domain  = get_option( 'eflt_freshsales_domain', '' );
        $api_key = get_option( 'eflt_freshsales_api_key', '' );
        if ( empty( $domain ) || empty( $api_key ) ) return;

        $contact = self::lookup_contact( $email, $domain, $api_key );
        if ( ! $contact ) {
            error_log( 'EFLT Freshsales: No contact found for ' . $email );
            return;
        }

        $contact_id    = $contact['id'];
        $existing_tags = $contact['tags'] ?? [];
        $tag           = 'Downloaded: ' . $pdf_label;

        if ( ! in_array( $tag, $existing_tags, true ) ) {
            $existing_tags[] = $tag;
            self::update_contact_tags( $contact_id, $existing_tags, $domain, $api_key );
        }

        self::add_contact_note( $contact_id, $pdf_label, $file_url, $domain, $api_key );
    }

    private static function lookup_contact( $email, $domain, $api_key ) {
        $url      = 'https://' . $domain . '/crm/sales/api/contacts/upsert';
        $response = wp_remote_post( $url, [
            'headers' => self::get_headers( $api_key ),
            'body'    => wp_json_encode( [
                'contact'           => [ 'email' => $email ],
                'unique_identifier' => [ 'emails' => $email ],
            ] ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( 'EFLT Freshsales upsert error: ' . $response->get_error_message() );
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! in_array( $code, [ 200, 201 ], true ) || empty( $body['contact'] ) ) {
            error_log( 'EFLT Freshsales upsert failed (HTTP ' . $code . '): ' . wp_remote_retrieve_body( $response ) );
            return null;
        }

        return $body['contact'];
    }

    private static function update_contact_tags( $contact_id, $tags, $domain, $api_key ) {
        wp_remote_request( 'https://' . $domain . '/crm/sales/api/contacts/' . $contact_id, [
            'method'  => 'PUT',
            'headers' => self::get_headers( $api_key ),
            'body'    => wp_json_encode( [ 'contact' => [ 'tags' => $tags ] ] ),
            'timeout' => 15,
        ] );
    }

    private static function add_contact_note( $contact_id, $pdf_label, $file_url, $domain, $api_key ) {
        wp_remote_post( 'https://' . $domain . '/crm/sales/api/notes', [
            'headers' => self::get_headers( $api_key ),
            'body'    => wp_json_encode( [
                'note' => [
                    'description'     => sprintf( "Downloaded PDF: %s\nFile: %s\nTime: %s", $pdf_label, $file_url, current_time( 'mysql' ) ),
                    'targetable_type' => 'Contact',
                    'targetable_id'   => $contact_id,
                ],
            ] ),
            'timeout' => 15,
        ] );
    }

    private static function get_headers( $api_key ) {
        return [
            'Authorization' => 'Token token=' . $api_key,
            'Content-Type'  => 'application/json',
        ];
    }
}
