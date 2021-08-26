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
    private $mode =  'live';

    /**
    * Initialize the plugin by setting localization, filters, and administration functions.
    *
    * @since     1.0
    */
    public function run() {

        $this->mode = defined( 'TYPEWHEEL_EDDE_ECOLOGI_TEST' ) && TYPEWHEEL_EDDE_ECOLOGI_TEST ? 'test' : 'live';

        add_action( 'edd_complete_download_purchase', [ $this, 'do_purchase_impact' ], 10, 3 );
        add_action( 'edd_recurring_add_subscription_payment', [ $this, 'do_renewal_impact' ], 10, 2 );

        add_filter( 'cron_schedules', [ $this, 'add_schedule' ] );

        if ( ! wp_next_scheduled( 'typewheel_edde_do_every_eight_hours' ) ) {
            wp_schedule_event( time(), 'every_eight_hours', 'typewheel_edde_do_every_eight_hours' );
        }

        add_action( 'typewheel_edde_do_every_eight_hours', [ $this, 'retrieve_total_impact' ] );

        add_action( 'edd_add_email_tags', [ $this, 'add_email_tags' ], 100 );

        $this->add_shortcodes();

        if ( apply_filters( 'typewheel-edd-ecologi/show_in_history', true ) ) {
            add_action( 'edd_purchase_history_header_after', [ $this, 'add_purchase_history_header' ], 100 );
            add_action( 'edd_purchase_history_row_end', [ $this, 'add_purchase_history_column' ], 100, 2 );
        }

        if ( apply_filters( 'typewheel-edd-ecologi/show_in_receipt', true ) ) {
            add_action( 'edd_payment_receipt_after', [ $this, 'add_purchase_receipt_row' ], 100, 2 );
        }

    }

    /*---------------------------------------------------------------------------------*
    * Public Functions
    *---------------------------------------------------------------------------------*/

    public function do_purchase_impact( $download_id, $payment_id, $download_type ) {

        $payment = new EDD_Payment( $payment_id );
        $customer = new EDD_Customer( $payment->customer_id );
        $this->mode = $payment->mode == 'test' || $payment->gateway == 'manual' ? 'test' : 'live';

        $ecologi = apply_filters( 'typewheel-edd-ecologi/impact', [] );

        if ( defined( 'TYPEWHEEL_EDDE_ECOLOGI_API_KEY' ) && is_array( $ecologi ) && array_key_exists( 'edd_purchase', $ecologi ) ) {

            $breakpoints = array_reverse( $ecologi['edd_purchase'], true );

            foreach ( $breakpoints as $amount => $impact ) {

                if ( $payment->total >= (int) $amount  ) {

                    if ( is_array( $impact ) && array_key_exists( 'trees', $impact ) && $impact['trees'] !== 0 ) $this->plant_trees( $impact['trees'], $payment, $customer );
                    if ( is_array( $impact ) && array_key_exists( 'carbon', $impact ) && $impact['carbon'] !== 0 ) $this->offset_carbon( $impact['carbon'], $payment, $customer );
                    break;

                }

            }

            $this->retrieve_total_impact();

        }

    }

    public function do_renewal_impact( $payment, $subscription ) {

        $customer = new EDD_Customer( $payment->customer_id );
        $this->mode = $payment->mode == 'test' || $payment->gateway == 'manual' ? 'test' : 'live';

        $ecologi = apply_filters( 'typewheel-edd-ecologi/impact', [] );

        if ( defined( 'TYPEWHEEL_EDDE_ECOLOGI_API_KEY' ) && is_array( $ecologi ) && array_key_exists( 'edd_renewal', $ecologi ) ) {

            $breakpoints = array_reverse( $ecologi['edd_renewal'], true );

            foreach ( $breakpoints as $amount => $impact ) {

                if ( $payment->total >= (int) $amount  ) {

                    if ( is_array( $impact ) && array_key_exists( 'trees', $impact ) && $impact['trees'] !== 0 ) $this->plant_trees( $impact['trees'], $payment, $customer );
                    if ( is_array( $impact ) && array_key_exists( 'carbon', $impact ) && $impact['carbon'] !== 0 ) $this->offset_carbon( $impact['carbon'], $payment, $customer );
                    break;

                }

            }

            $this->retrieve_total_impact();

        }

    }

    public function plant_trees( $trees, $payment, $customer ) {

        $body = array(
            'number' => $trees,
            'test' => $this->mode == 'test'
        );

        $funded_by = apply_filters( 'typewheel-edd-ecologi/funded_by', '', $payment, $customer );

        if ( $funded_by != '' ) {
            $body['name'] = $funded_by;
        }

        $response = wp_remote_post( "{$this->api}/impact/trees", array(
            'headers' => array(
                'Authorization' => 'Bearer ' . TYPEWHEEL_EDDE_ECOLOGI_API_KEY,
                'Content-Type'  => 'application/json'
            ),
            'body' => json_encode( $body )
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
                'units' => 'KG',
                'test' => $this->mode == 'test'
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

        }

    }

    public static function retrieve_total_impact( $username = TYPEWHEEL_EDDE_ECOLOGI_USERNAME ) {

        $response = wp_remote_get( "https://public.ecologi.com/users/{$username}/impact" );

        if ( ! is_wp_error( $response ) ) {

            $impact = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( $username == TYPEWHEEL_EDDE_ECOLOGI_USERNAME ) {

                update_option( 'typewheel_edd_ecologi_impact', $impact );

            }

            return $impact;

        } else {

            return false;

        }

    }

    public function add_schedule( $schedules ) {

        // add an every three hours schedule to the available cron schedules
        $schedules['every_eight_hours'] = array(
            'interval' => 8 * HOUR_IN_SECONDS,
            'display' => __('Every eight hours')
        );

        return $schedules;

    }

    public function add_email_tags() {

		edd_add_email_tag( 'ecologi_purchase_tree_count', 'Display the number of trees planted with this purchase', array( $this, 'render_email_tag_ecologi_purchase_tree_count' ) );
		edd_add_email_tag( 'ecologi_purchase_tree_url', 'Display the unique URL of the trees planted with this purchase', array( $this, 'render_email_tag_ecologi_purchase_tree_url' ) );
		edd_add_email_tag( 'ecologi_purchase_carbon_offset', 'Display the amount of carbon offset with this purchase', array( $this, 'render_email_tag_ecologi_purchase_carbon_offset' ) );
		edd_add_email_tag( 'ecologi_purchase_carbon_projects', 'Display the projects involved in carbon offset with this purchase', array( $this, 'render_email_tag_ecologi_purchase_carbon_projects' ) );

		edd_add_email_tag( 'ecologi_customer_tree_count', 'Display the number of trees planted by this customer', array( $this, 'render_email_tag_ecologi_customer_tree_count' ) );
		edd_add_email_tag( 'ecologi_customer_carbon_offset', 'Display the amount of carbon offset by this customer', array( $this, 'render_email_tag_ecologi_customer_carbon_offset' ) );

		edd_add_email_tag( 'ecologi_tree_count', 'Display the total number of trees planted by your ecologi user', array( $this, 'render_email_tag_ecologi_tree_count' ) );
		edd_add_email_tag( 'ecologi_carbon_offset', 'Display the amount of carbon offset by your ecologi user', array( $this, 'render_email_tag_ecologi_carbon_offset' ) );

	}

    public function add_shortcodes() {

        add_shortcode( 'ecologi_customer_tree_count', [ $this, 'render_shortcode_ecologi_customer_tree_count'] );
        add_shortcode( 'ecologi_customer_carbon_offset', [ $this, 'render_shortcode_ecologi_customer_carbon_offset'] );
        add_shortcode( 'ecologi_tree_count', [ $this, 'render_shortcode_ecologi_tree_count'] );
        add_shortcode( 'ecologi_carbon_offset', [ $this, 'render_shortcode_ecologi_carbon_offset'] );

    }

	public function render_email_tag_ecologi_purchase_tree_count( $payment_id = 0 ) {

        $trees = Typewheel_EDD_Ecologi::get_trees( 'payment', $payment_id );

        $tree_count = $trees ? sprintf( _n( '%s tree', '%s trees', $trees['count'] ), $trees['count'] ) : '';

		return $tree_count;

	}

	public function render_email_tag_ecologi_purchase_tree_url( $payment_id = 0 ) {

        $trees = Typewheel_EDD_Ecologi::get_trees( 'payment', $payment_id );

        $tree_url = $trees ? $trees['url'] : '';

		return $trees_url;

	}

	public function render_email_tag_ecologi_purchase_carbon_offset( $payment_id = 0 ) {

        $carbon = Typewheel_EDD_Ecologi::get_carbon( 'payment', $payment_id );

        $carbon_offset = $carbon ? $carbon['number'] . ' kgCO<sub>2</sub>e' : '';

		return $carbon_offset;

	}

	public function render_email_tag_ecologi_purchase_carbon_projects( $payment_id = 0 ) {

        $carbon = Typewheel_EDD_Ecologi::get_carbon( 'payment', $payment_id );
        $html = '';

        if ( $carbon ) {

            $html .= '<table><thead><tr><th>Project(s)</th><th colspan="2" style="width: 100px; text-align: right;">Carbon Offset</th></thead><tbody>';

            foreach ( $carbon['projects'] as $project ) {

                $kilograms = $project['splitAmountTonnes'] * 1000;

                $html .= "<tr><td>{$project['name']}</td><td style='text-align: right;'>{$project['splitPercentage']}%</td><td style='text-align: right;'>{$kilograms} KG</td></tr>";

            }

            $html .= '</tbody></table>';

        }

		return $html;

	}

    public function render_email_tag_ecologi_customer_tree_count( $payment_id = 0 ) {

        $customer_id = ( new EDD_Payment( $payment_id ) )->customer_id;

        $trees = Typewheel_EDD_Ecologi::get_trees( 'customer', $customer_id );

        return sprintf( _n( '%s tree', '%s trees', $trees['count'] ), $trees['count'] );

    }


	public function render_email_tag_ecologi_customer_carbon_offset( $payment_id = 0 ) {

        $customer_id = ( new EDD_Payment( $payment_id ) )->customer_id;

        $carbon = Typewheel_EDD_Ecologi::get_carbon( 'customer', $customer_id );

		return $carbon['number'] . ' KG';

	}

    public function render_email_tag_ecologi_tree_count( $payment_id = 0 ) {

        $trees = Typewheel_EDD_Ecologi::get_trees();

		return sprintf( _n( '%s tree', '%s trees', $trees ), $trees );

	}

	public function render_email_tag_ecologi_carbon_offset( $payment_id = 0 ) {

        $carbon = Typewheel_EDD_Ecologi::get_carbon();

		return $carbon . ' tonnes';

	}

    public function render_shortcode_ecologi_customer_tree_count( $atts ) {

        $customer_id = ( new EDD_Customer( get_current_user_id(), true ) )->id;

        $trees = Typewheel_EDD_Ecologi::get_trees( 'customer', $customer_id );

        return $trees['count'];

    }

    public function render_shortcode_ecologi_customer_carbon_offset( $atts ) {

        extract( shortcode_atts( array(
            'units' => 'tonnes'
        ), $atts ) );

        $customer_id = ( new EDD_Customer( get_current_user_id(), true ) )->id;

        $carbon = Typewheel_EDD_Ecologi::get_carbon( 'customer', $customer_id );

        $carbon_offset = $units == 'kg' ? $carbon['number'] : $carbon['number'] / 1000;

        return $carbon_offset;

    }

    public function render_shortcode_ecologi_tree_count( $atts ) {

        return Typewheel_EDD_Ecologi::get_trees();

    }

    public function render_shortcode_ecologi_carbon_offset( $atts ) {

        extract( shortcode_atts( array(
            'units' => 'tonnes'
        ), $atts ) );

        $carbon = Typewheel_EDD_Ecologi::get_carbon();

        $carbon_offset = $units == 'kg' ? $carbon * 1000 : $carbon;

        return $carbon_offset;

    }

    public static function get_trees( $related_to = null, $object_id = 0 ) {

        switch ( $related_to ) {

            case 'payment':
                return ( new EDD_Payment( $object_id ) )->get_meta( 'typewheel_edd_ecologi_impact_trees', true );

            case 'customer':
                return ( new EDD_Customer( $object_id ) )->get_meta( 'typewheel_edd_ecologi_impact_trees', true );

            case 'user_wp':
                return ( new EDD_Customer( $object_id, true ) )->get_meta( 'typewheel_edd_ecologi_impact_trees', true );

            case 'user_ecologi':
            default:
                $impact = get_option( 'typewheel_edd_ecologi_impact' );
                return $impact['trees'];

        }

    }

    public static function get_carbon( $related_to = null, $object_id = 0 ) {

        switch ( $related_to ) {

            case 'payment':
                return ( new EDD_Payment( $object_id ) )->get_meta( 'typewheel_edd_ecologi_impact_carbon', true );

            case 'customer':
                return ( new EDD_Customer( $object_id ) )->get_meta( 'typewheel_edd_ecologi_impact_carbon', true );

            case 'user_wp':
                return ( new EDD_Customer( $object_id, true ) )->get_meta( 'typewheel_edd_ecologi_impact_carbon', true );

            case 'user_ecologi':
            default:
                $impact = get_option( 'typewheel_edd_ecologi_impact' );
                return $impact['carbonOffset']; // in tonnes

        }

    }

    public function add_purchase_history_header() {

        echo '<th class="edd_purchase_impact">Impact</th>';

    }

    public function add_purchase_history_column( $payment_id, $payment_meta ) {

        $trees = ( new EDD_Payment( $payment_id ) )->get_meta( 'typewheel_edd_ecologi_impact_trees', true );
        $carbon = ( new EDD_Payment( $payment_id ) )->get_meta( 'typewheel_edd_ecologi_impact_carbon', true );

        if ( $trees || $carbon ) {

            $separator = $trees['count'] && $carbon['number'] ? ' & ' : '';

            echo "<td class='edd_purchase_trees'><a href='{$trees['url']}'>" . $this->render_email_tag_ecologi_purchase_tree_count( $payment_id ) . "</a>${separator}" . $this->render_email_tag_ecologi_purchase_carbon_offset( $payment_id ) . "</td>";

        } else {

            echo '<td></td>';

        }


    }

    public function add_purchase_receipt_row( $payment, $edd_receipt_args ) {

        $trees = ( new EDD_Payment( $payment->ID ) )->get_meta( 'typewheel_edd_ecologi_impact_trees', true );

        echo '<tr><td valign="top"><strong>Ecologi Impact:</strong></td>';
        echo '<td class="edd_purchase_impact">';
        echo '<a href="' . $trees['url'] . '" style="display:block;margin-bottom: .75em;">' . $this->render_email_tag_ecologi_purchase_tree_count( $payment->ID ) . '</a>';
        echo $this->render_email_tag_ecologi_purchase_carbon_projects( $payment->ID );
        echo '</td>';
        echo '</tr>';

    }

}
