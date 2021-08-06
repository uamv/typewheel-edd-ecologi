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

    private $api = 'https://public.ecologi.com';

    /**
    * Initialize the plugin by setting localization, filters, and administration functions.
    *
    * @since     1.0
    */
    public function run() {

        add_action( 'edd_complete_download_purchase', [ $this, 'do_purchase_impact' ], 10, 3 );
        add_action( 'edd_recurring_record_payment', [ $this, 'do_renewal_impact' ], 10, 5 );

        add_filter( 'cron_schedules', [ $this, 'add_schedule' ] );

        if ( ! wp_next_scheduled( 'typewheel_edde_do_every_three_hours' ) ) {
            wp_schedule_event( time(), 'every_three_hours', 'typewheel_edde_do_every_three_hours' );
        }

        add_action( 'typewheel_edde_do_every_three_hours', [ $this, 'retrieve_total_impact' ] );

        add_action( 'edd_add_email_tags', [ $this, 'add_email_tags' ], 100 );

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

        $response = wp_remote_post( "{$this->api}/impact/trees", array(
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

            $payment->update_meta( 'typewheel_edd_ecologi_impact_trees', array( 'count' => $trees, 'url' => $planted['treeUrl'] ) );

            $customer_trees = $customer->get_meta( 'typewheel_edd_ecologi_impact_trees' );

            if ( ! $customer_trees ) {

                $customer->add_meta( 'typewheel_edd_ecologi_impact_trees', array( 'count' => $trees, 'urls' => array( $planted['treeUrl'] ) ) );

            } else {

                $customer_trees['count'] = $customer_trees['count'] + $trees;
                $customer_trees['urls'][] = $planted['treeUrl'];

                $customer->update_meta( 'typewheel_edd_ecologi_impact_trees', $customer_trees );

            }

            $this->retrieve_total_impact();

        }

    }

    public function offset_carbon( $kilograms, $payment, $customer ) {

        $response = wp_remote_post( "{$this->api}/impact/carbon", array(
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

            $payment->update_meta( 'typewheel_edd_ecologi_impact_carbon', array( 'number' => $offset['number'], 'projects' => $offset['projectDetails'] ) );

            $customer_offset = $customer->get_meta( 'typewheel_edd_ecologi_impact_carbon' );

            if ( ! $customer_offset ) {

                $customer->add_meta( 'typewheel_edd_ecologi_impact_carbon', array( 'number' => $offset['number'], 'projects' => array( $offset['projectDetails'] ) ) );

            } else {

                $customer_offset['number'] = $customer_offset['number'] + $offset['number'];
                $customer_offset['projects'][] = $offset['projectDetails'];

                $customer->update_meta( 'typewheel_edd_ecologi_impact_carbon', $customer_offset );

            }

            $this->retrieve_total_impact();

        }

    }

    public function retrieve_total_impact() {

        $response = wp_remote_get( "{$this->api}/users/" . TYPEWHEEL_EDDE_ECOLOGI_USERNAME . "/impact" );

        if ( ! is_wp_error( $response ) ) {

            $impact = json_decode( wp_remote_retrieve_body( $response ), true );

            update_option( 'typewheel_edd_ecologi_impact', $impact );

        }

    }

    public function add_schedule( $schedules ) {

        // add an every three hours schedule to the available cron schedules
        $schedules['every_three_hours'] = array(
            'interval' => 3 * HOUR_IN_SECONDS,
            'display' => __('Every three hours')
        );

        return $schedules;

    }

    public function add_email_tags() {

		edd_add_email_tag( 'ecologi_purchase_tree_count', 'Display the number of trees planted with this purchase', array( $this, 'render_ecologi_purchase_tree_count' ) );
		edd_add_email_tag( 'ecologi_purchase_tree_url', 'Display the unique URL of the trees planted with this purchase', array( $this, 'render_ecologi_purchase_tree_url' ) );
		edd_add_email_tag( 'ecologi_purchase_carbon_offset', 'Display the amount of carbon offset with this purchase', array( $this, 'render_ecologi_purchase_carbon_offset' ) );
		edd_add_email_tag( 'ecologi_purchase_carbon_projects', 'Display the projects involved in carbon offset with this purchase', array( $this, 'render_ecologi_purchase_carbon_projects' ) );

		edd_add_email_tag( 'ecologi_customer_tree_count', 'Display the number of trees planted by this customer', array( $this, 'render_ecologi_customer_tree_count' ) );
		edd_add_email_tag( 'ecologi_customer_tree_url', 'Display the unique URLs of the trees planted by this customer', array( $this, 'render_ecologi_customer_tree_url' ) );
		edd_add_email_tag( 'ecologi_customer_carbon_offset', 'Display the amount of carbon offset by this customer', array( $this, 'render_ecologi_customer_carbon_offset' ) );

		edd_add_email_tag( 'ecologi_user_tree_count', 'Display the total number of trees planted by your ecologi user', array( $this, 'render_ecologi_user_tree_count' ) );
		edd_add_email_tag( 'ecologi_user_carbon_offset', 'Display the amount of carbon offset by your ecologi user', array( $this, 'render_ecologi_user_carbon_offset' ) );

	}

	public function render_ecologi_purchase_tree_count( $payment_id = 0 ) {

        $trees = ( new EDD_Payment( $payment_id ) )->get_meta( 'typewheel_edd_ecologi_impact_trees', true );

		return sprintf( _n( '%s tree', '%s trees', $trees['count'] ), $trees['count'] );

	}

	public function render_ecologi_purchase_tree_url( $payment_id = 0 ) {

        $trees = ( new EDD_Payment( $payment_id ) )->get_meta( 'typewheel_edd_ecologi_impact_trees', true );

		return $trees['url'];

	}

	public function render_ecologi_purchase_carbon_offset( $payment_id = 0 ) {

        $carbon = ( new EDD_Payment( $payment_id ) )->get_meta( 'typewheel_edd_ecologi_impact_carbon', true );

		return $carbon['number'] . ' KG';

	}

	public function render_ecologi_purchase_carbon_projects( $payment_id = 0 ) {

        $carbon = ( new EDD_Payment( $payment_id ) )->get_meta( 'typewheel_edd_ecologi_impact_carbon', true );

        $html = '<table><thead><tr><th>Project(s)</th><th colspan="2" style="width: 100px; text-align: right;">Carbon Offset</th></thead><tbody>';

        foreach ( $carbon['projects'] as $project ) {

            $kilograms = $project['splitAmountTonnes'] * 1000;

            $html .= "<tr><td>{$project['name']}</td><td style='text-align: right;'>{$project['splitPercentage']}%</td><td style='text-align: right;'>{$kilograms} KG</td></tr>";

        }

        $html .= '</tbody></table>';

		return $html;

	}

	public function render_ecologi_customer_tree_count( $payment_id = 0 ) {

        $customer_id = ( new EDD_Payment( $payment_id ) )->customer_id;

        $trees = ( new EDD_Customer( $customer_id ) )->get_meta( 'typewheel_edd_ecologi_impact_trees', true );

		return sprintf( _n( '%s tree', '%s trees', $trees['count'] ), $trees['count'] );

	}

	public function render_ecologi_customer_tree_url( $payment_id = 0 ) {

        $customer_id = ( new EDD_Payment( $payment_id ) )->customer_id;

        $trees = ( new EDD_Customer( $customer_id ) )->get_meta( 'typewheel_edd_ecologi_impact_trees', true );

		return $trees['url'];

	}

	public function render_ecologi_customer_carbon_offset( $payment_id = 0 ) {

        $customer_id = ( new EDD_Payment( $payment_id ) )->customer_id;

        $carbon = ( new EDD_Customer( $customer_id ) )->get_meta( 'typewheel_edd_ecologi_impact_carbon', true );

		return $carbon['number'] . ' KG';

	}

    public function render_ecologi_user_tree_count( $payment_id = 0 ) {

        $impact = get_option( 'typewheel_edd_ecologi_impact' );

		return sprintf( _n( '%s tree', '%s trees', $impact['trees'] ), $impact['trees'] );

	}

	public function render_ecologi_user_carbon_offset( $payment_id = 0 ) {

        $impact = get_option( 'typewheel_edd_ecologi_impact' );

		return $impact['carbonOffset'] . ' tonnes';

	}




}
