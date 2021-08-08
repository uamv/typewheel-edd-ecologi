## Credit & Gratitude
Should you like to show your appreciation for this contribution to the community, I ask that you [help plant this forest](https://ecologi.com/gravityhopper?r=6102fa245a5109238b1f2de6) for [Gravity Hopper](https://gravityhopper.com) – the first site upon which this plugin has run.

## Installation & Setup
After installation, do the following to begin processing impact requests to the [ecologi API](https://docs.ecologi.com/docs/public-api-docs/API/Impact-API.v1.yaml).

### Retrieve API Key
Within your ecologi account, create an API key for usage with EDD purchases.

### Add API Key
Within `wp-config.php`, add your API key and ecologi username as a constant.

```
define( 'TYPEWHEEL_EDDE_ECOLOGI_API_KEY', 'your-key-goes-here' );
define( 'TYPEWHEEL_EDDE_ECOLOGI_USERNAME', 'your-username-goes-here' );
```

### Configure Impact Breakpoints
Return an array of ecologi impact that will be run for various purchase and renewal amounts. The impact array you set for a value will apply to all values above the associated key and up to the next associated key.
```
add_filter( 'typewheel_edd_ecologi_impact', function( $impact ) {

    return array(
        'edd_purchase' => array
            '0'   => [ 'trees' => 2, 'carbon' => 0 ],
            '42'  => [ 'trees' => 4, 'carbon' => 0 ],
            '75'  => [ 'trees' => 7, 'carbon' => 0 ],
            '100' => [ 'trees' => 11, 'carbon' => 0 ],
            '150' => [ 'trees' => 20, 'carbon' => 0 ]
        ),
        'edd_renewal' => array(
            '0'   => [ 'trees' => 1, 'carbon' => 75 ],
            '42'  => [ 'trees' => 2, 'carbon' => 150 ],
            '75'  => [ 'trees' => 3, 'carbon' => 250 ],
            '100' => [ 'trees' => 4, 'carbon' => 500 ],
            '150' => [ 'trees' => 5, 'carbon' => 500 ]
        )
    );

} );
```

## Email Tags
The following tags can be used in EDD email templates. Formatted with units & text.

### Purchase Related
`{ecologi_purchase_tree_count}` - Display the number of trees planted with this purchase.
`{ecologi_purchase_tree_url}` - Display the unique URL of the trees planted with this purchase.
`{ecologi_purchase_carbon_offset}` - Display the amount of carbon offset with this purchase *(in kg)*
`{ecologi_purchase_carbon_projects}` - Display the projects involved in carbon offset with this purchase.

### Customer Related
`{ecologi_customer_tree_count}` - Display the number of trees planted by this customer.
`{ecologi_customer_carbon_offset}` - Display the amount of carbon offset by this customer. *(in kg)*

### ecologi User Related
`{ecologi_tree_count}` - Display the total number of trees planted by your ecologi user.
`{ecologi_carbon_offset}` - Display the amount of carbon offset by your ecologi user. *(in tonnes)*

## Shortcodes
The following shortcodes can be used throughout your site. Returns number value only.

### Customer Related
`[ecologi_customer_tree_count]`
`[ecologi_customer_carbon_offset]` *accepts units="kg" parameter (default is tonnes)*

### ecologi User Related
`[ecologi_tree_count]`
`[ecologi_carbon_offset]` *accepts units="kg" parameter (default is tonnes)*


## Impact Tracking
With each purchase and renewal that touches the ecologi Impact API, the plugin will save meta to both the EDD Payment and EDD Customer object. This data is structured as:

### Payment Meta
```
$payment->typewheel_edd_ecologi_impact_trees = array(
    'count' => 40,
    'url'   => 'https://ecologi.com/gravityhopper?tree='
);
$payment->typewheel_edd_ecologi_impact_carbon = array(
    'number'   => 2000,
    'projects' => array(
        array(
            'name' => 'Cleaner cookstoves in Zambia and Ghana',
            'splitPercentage' => 25,
            'splitAmountTonnes' => .05
        ),
        array(
            'name' => 'Producing energy from waste rice husks in India',
            'splitPercentage' => 75,
            'splitAmountTonnes' => .15
        )
    )
);
```

### Customer Meta
```
$customer->typewheel_edd_ecologi_impact_trees = array(
    'count' => 40,
    'url'   => array(
        'https://ecologi.com/gravityhopper?tree='
    )
);
$customer->typewheel_edd_ecologi_impact_carbon = array(
    'number'   => 2000,
    'projects' => array (
        array(
            array(
                'name' => 'Cleaner cookstoves in Zambia and Ghana',
                'splitPercentage' => 25,
                'splitAmountTonnes' => .05
            ),
            array(
                'name' => 'Producing energy from waste rice husks in India',
                'splitPercentage' => 75,
                'splitAmountTonnes' => .15
            )
        )
    )
);
```

### Total impact
The total impact of your ecologi user is also retrieved every three hours and during each transaction/renewal. This is saved to options as:
```
'typewheel_edd_ecologi_impact' => array(
    'trees' => 3249,
    'carbonOffset' => 2.3
    )
```
