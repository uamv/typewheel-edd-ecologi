<?php

/**
* Typewheel_EDD_Ecologi Class
*
* @package Typewheel_EDD_Ecologi
* @author  uamv
*/
class Typewheel_EDD_Ecologi {

    /*---------------------------------------------------------------------------------*
    * Constructor
    *---------------------------------------------------------------------------------*/

    /**
    * Initialize the plugin by setting localization, filters, and administration functions.
    *
    * @since     1.0
    */
    public function run() {

        add_action( 'edd_complete_download_purchase', [ $this, 'do_purchase_impact' ], 10, 3 );
        add_action( 'edd_recurring_record_payment', [ $this, 'do_renewal_impact' ], 10, 5 );

    }

    /*---------------------------------------------------------------------------------*
    * Public Functions
    *---------------------------------------------------------------------------------*/

    public function do_purchase_impact( $download_id, $payment_id, $download_type ) {

        $payment = new EDD_Payment( $payment_id );
        $customer = new EDD_Customer( $payment->customer_id );

        $ecologi = apply_filters( 'typewheel_edd_ecologi_impact', [] );

        if ( $payment->mode != 'test' && defined( 'TYPEWHEEL_EDDE_ECOLOGI_API_KEY' ) && is_array( $ecologi ) && array_key_exists( 'edd_purchase', $ecologi ) ) {

            $breakpoints = array_reverse( $ecologi['edd_purchase'], true );

            foreach ( $breakpoints as $amount => $impact ) {

                if ( $payment->total > (int) $amount  ) {

                    if ( is_array( $impact ) && array_key_exists( 'trees', $impact ) && $impact['trees'] !== 0 ) $this->plant_trees( $impact['trees'], $payment, $customer );
                    if ( is_array( $impact ) && array_key_exists( 'carbon', $impact ) && $impact['carbon'] !== 0 ) $this->offset_carbon( $impact['carbon'], $payment, $customer );
                    break;

                }

            }

        }

    }

    public function do_renewal_impact( $payment, $parent_id, $amount, $txn_id, $unique_key ) {

        $customer = new EDD_Customer( $payment->customer_id );

        $ecologi = apply_filters( 'typewheel_edd_ecologi_impact', [] );

        if ( $payment->mode != 'test' && defined( 'TYPEWHEEL_EDDE_ECOLOGI_API_KEY' ) && is_array( $ecologi ) && array_key_exists( 'edd_renewal', $ecologi ) ) {

            $breakpoints = array_reverse( $ecologi['edd_renewal'] );

            foreach ( $breakpoints as $amount => $impact ) {

                if ( $payment->total > (int) $amount  ) {

                    if ( is_array( $impact ) && array_key_exists( 'trees', $impact ) && $impact['trees'] !== 0 ) $this->plant_trees( $impact['trees'], $payment, $customer );
                    if ( is_array( $impact ) && array_key_exists( 'carbon', $impact ) && $impact['carbon'] !== 0 ) $this->offset_carbon( $impact['carbon'], $payment, $customer );
                    break;

                }

            }

        }

    }

    public function plant_trees( $trees, $payment, $customer ) {

        $response = wp_remote_post( 'https://public.ecologi.com/impact/trees', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . TYPEWHEEL_EDDE_ECOLOGI_API_KEY,
                'Content-Type'  => 'application/json'
            ),
            'body' => json_encode( array(
                'number' => $trees
            ) )
        ) );

        if ( ! is_wp_error( $response ) ) {

            $planted = json_decode( wp_remote_retrieve_body( $response ), true );

            $payment->update_meta( '_typewheel_edd_ecologi_impact_trees', array( 'count' => $trees, 'url' => $planted['treeUrl'] ) );

            $customer_trees = $customer->get_meta( '_typewheel_edd_ecologi_impact_trees' );

            if ( ! $customer_trees ) {

                $customer->add_meta( '_typewheel_edd_ecologi_impact_trees', array( 'count' => $trees, 'urls' => array( $planted['treeUrl'] ) ) );

            } else {

                $customer_trees['count'] = $customer_trees['count'] + $trees;
                $customer_trees['urls'][] = $planted['treeUrl'];

                $customer->update_meta( '_typewheel_edd_ecologi_impact_trees', $customer_trees );

            }

        }

    }

    public function offset_carbon( $kilograms, $payment, $customer ) {

        $response = wp_remote_post( 'https://public.ecologi.com/impact/carbon', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . TYPEWHEEL_EDDE_ECOLOGI_API_KEY,
                'Content-Type'  => 'application/json'
            ),
            'body' => json_encode( array(
                'number' => $kilograms,
                'units' => 'KG'
            ) )
        ) );

        if ( ! is_wp_error( $response ) ) {

            $offset = json_decode( wp_remote_retrieve_body( $response ), true );

            $payment->update_meta( '_typewheel_edd_ecologi_impact_carbon', array( 'number' => $offset['number'], 'projects' => $offset['projectDetails'] ) );

            $customer_offset = $customer->get_meta( '_typewheel_edd_ecologi_impact_carbon' );

            if ( ! $customer_offset ) {

                $customer->add_meta( '_typewheel_edd_ecologi_impact_carbon', array( 'number' => $offset['number'], 'projects' => array( $offset['projectDetails'] ) ) );

            } else {

                $customer_offset['number'] = $customer_offset['number'] + $offset['number'];
                $customer_offset['projects'][] = $offset['projectDetails'];

                $customer->update_meta( '_typewheel_edd_ecologi_impact_carbon', $customer_offset );

            }

        }

    }

}
