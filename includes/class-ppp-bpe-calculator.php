<?php
/**
 * Book price calculator — local pricing engine.
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;

class PPP_BPE_Calculator {

	private const BOOK_SIZES = array(
		'A4'     => array( 'label' => 'A4 (210 × 297 mm)', 'width' => 210, 'height' => 297 ),
		'A5'     => array( 'label' => 'A5 (148 × 210 mm)', 'width' => 148, 'height' => 210 ),
		'letter' => array( 'label' => 'Letter (8.5 × 11 in)', 'width' => 216, 'height' => 279 ),
		'digest' => array( 'label' => 'Digest (5.5 × 8.5 in)', 'width' => 140, 'height' => 216 ),
		'pocket' => array( 'label' => 'Pocket (4.25 × 6.87 in)', 'width' => 108, 'height' => 175 ),
	);

	private const PAPER_TYPES = array(
		'80gsm_offset'  => array( 'label' => '80 gsm Offset', 'cost_per_page' => 0.012 ),
		'100gsm_offset' => array( 'label' => '100 gsm Offset', 'cost_per_page' => 0.018 ),
		'115gsm_coated' => array( 'label' => '115 gsm Coated', 'cost_per_page' => 0.025 ),
		'150gsm_coated' => array( 'label' => '150 gsm Coated', 'cost_per_page' => 0.035 ),
	);

	private const BINDING_TYPES = array(
		'perfect'      => array( 'label' => 'Perfect Binding', 'cost' => 2.50, 'min_pages' => 40 ),
		'saddle_stitch' => array( 'label' => 'Saddle Stitch', 'cost' => 1.00, 'max_pages' => 80 ),
		'hardcover'    => array( 'label' => 'Hardcover', 'cost' => 8.00, 'min_pages' => 40 ),
		'spiral'       => array( 'label' => 'Spiral Binding', 'cost' => 3.00 ),
	);

	private const COLOR_MULTIPLIERS = array(
		'bw'    => 1.0,
		'color' => 2.5,
	);

	private const COVER_COSTS = array(
		'bw'    => 1.50,
		'color' => 3.00,
	);

	private const SETUP_COST = 5.00;

	private const COUNTRIES = array(
		'ES' => 'Spain',
		'DE' => 'Germany',
		'FR' => 'France',
		'IT' => 'Italy',
		'PT' => 'Portugal',
		'GB' => 'United Kingdom',
		'US' => 'United States',
		'MX' => 'Mexico',
		'AR' => 'Argentina',
		'CO' => 'Colombia',
		'CL' => 'Chile',
		'PE' => 'Peru',
	);

	public function get_form_options(): array {
		$sizes = array();
		foreach ( self::BOOK_SIZES as $key => $data ) {
			$sizes[ $key ] = $data['label'];
		}

		$papers = array();
		foreach ( self::PAPER_TYPES as $key => $data ) {
			$papers[ $key ] = $data['label'];
		}

		$bindings = array();
		foreach ( self::BINDING_TYPES as $key => $data ) {
			$bindings[ $key ] = $data['label'];
		}

		return array(
			'book_sizes'      => $sizes,
			'paper_types'     => $papers,
			'binding_types'   => $bindings,
			'interior_colors' => array(
				'bw'    => __( 'Black & White', 'printpricepro-bpe' ),
				'color' => __( 'Color', 'printpricepro-bpe' ),
			),
			'cover_colors'    => array(
				'bw'    => __( 'Black & White', 'printpricepro-bpe' ),
				'color' => __( 'Color', 'printpricepro-bpe' ),
			),
			'countries'       => self::COUNTRIES,
			'binding_rules'   => $this->get_binding_rules(),
		);
	}

	private function get_binding_rules(): array {
		$rules = array();
		foreach ( self::BINDING_TYPES as $key => $data ) {
			$rule = array();
			if ( isset( $data['min_pages'] ) ) {
				$rule['min_pages'] = $data['min_pages'];
			}
			if ( isset( $data['max_pages'] ) ) {
				$rule['max_pages'] = $data['max_pages'];
			}
			if ( ! empty( $rule ) ) {
				$rules[ $key ] = $rule;
			}
		}
		return $rules;
	}

	/**
	 * @return array|WP_Error Sanitized specs array or WP_Error with validation messages.
	 */
	public function validate_specs( array $specs ): array|WP_Error {
		$errors = new WP_Error();

		$book_size = sanitize_text_field( $specs['book_size'] ?? '' );
		if ( ! isset( self::BOOK_SIZES[ $book_size ] ) ) {
			$errors->add( 'book_size', __( 'Invalid book size.', 'printpricepro-bpe' ) );
		}

		$pages = absint( $specs['pages'] ?? 0 );
		if ( $pages < 8 || $pages > 1000 ) {
			$errors->add( 'pages', __( 'Pages must be between 8 and 1000.', 'printpricepro-bpe' ) );
		} elseif ( 0 !== $pages % 2 ) {
			$errors->add( 'pages', __( 'Page count must be even.', 'printpricepro-bpe' ) );
		}

		$copies = absint( $specs['copies'] ?? 0 );
		if ( $copies < 1 || $copies > 10000 ) {
			$errors->add( 'copies', __( 'Copies must be between 1 and 10,000.', 'printpricepro-bpe' ) );
		}

		$interior_color = sanitize_text_field( $specs['interior_color'] ?? '' );
		if ( ! isset( self::COLOR_MULTIPLIERS[ $interior_color ] ) ) {
			$errors->add( 'interior_color', __( 'Invalid interior color mode.', 'printpricepro-bpe' ) );
		}

		$cover_color = sanitize_text_field( $specs['cover_color'] ?? '' );
		if ( ! isset( self::COVER_COSTS[ $cover_color ] ) ) {
			$errors->add( 'cover_color', __( 'Invalid cover color mode.', 'printpricepro-bpe' ) );
		}

		$binding = sanitize_text_field( $specs['binding'] ?? '' );
		if ( ! isset( self::BINDING_TYPES[ $binding ] ) ) {
			$errors->add( 'binding', __( 'Invalid binding type.', 'printpricepro-bpe' ) );
		} elseif ( $pages >= 8 ) {
			$binding_data = self::BINDING_TYPES[ $binding ];
			if ( isset( $binding_data['min_pages'] ) && $pages < $binding_data['min_pages'] ) {
				$errors->add(
					'binding',
					sprintf(
						/* translators: 1: binding type label, 2: minimum page count */
						__( '%1$s requires at least %2$d pages.', 'printpricepro-bpe' ),
						$binding_data['label'],
						$binding_data['min_pages']
					)
				);
			}
			if ( isset( $binding_data['max_pages'] ) && $pages > $binding_data['max_pages'] ) {
				$errors->add(
					'binding',
					sprintf(
						/* translators: 1: binding type label, 2: maximum page count */
						__( '%1$s supports a maximum of %2$d pages.', 'printpricepro-bpe' ),
						$binding_data['label'],
						$binding_data['max_pages']
					)
				);
			}
		}

		$paper = sanitize_text_field( $specs['paper'] ?? '' );
		if ( ! isset( self::PAPER_TYPES[ $paper ] ) ) {
			$errors->add( 'paper', __( 'Invalid paper type.', 'printpricepro-bpe' ) );
		}

		$country = strtoupper( sanitize_text_field( $specs['country'] ?? 'ES' ) );

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return array(
			'book_size'      => $book_size,
			'pages'          => $pages,
			'copies'         => $copies,
			'interior_color' => $interior_color,
			'cover_color'    => $cover_color,
			'binding'        => $binding,
			'paper'          => $paper,
			'country'        => $country,
		);
	}

	public function calculate( array $specs ): array {
		$paper_data   = self::PAPER_TYPES[ $specs['paper'] ];
		$binding_data = self::BINDING_TYPES[ $specs['binding'] ];
		$color_mult   = self::COLOR_MULTIPLIERS[ $specs['interior_color'] ];

		$interior_cost = $specs['pages'] * $paper_data['cost_per_page'] * $color_mult;
		$cover_cost    = self::COVER_COSTS[ $specs['cover_color'] ];
		$binding_cost  = $binding_data['cost'];
		$setup_cost    = self::SETUP_COST;

		$unit_cost = $interior_cost + $cover_cost + $binding_cost + $setup_cost;
		$total     = round( $unit_cost * $specs['copies'], 2 );
		$unit_cost = round( $unit_cost, 2 );

		$options  = get_option( PPP_BPE_Settings::OPTION_NAME, array() );
		$currency = $options['default_currency'] ?? 'EUR';

		return array(
			'breakdown'  => array(
				'interior_cost' => round( $interior_cost, 2 ),
				'cover_cost'    => $cover_cost,
				'binding_cost'  => $binding_cost,
				'setup_cost'    => $setup_cost,
			),
			'unit_price' => $unit_cost,
			'copies'     => $specs['copies'],
			'total'      => $total,
			'currency'   => $currency,
			'specs'      => array(
				'book_size'      => self::BOOK_SIZES[ $specs['book_size'] ]['label'],
				'pages'          => $specs['pages'],
				'copies'         => $specs['copies'],
				'interior_color' => 'color' === $specs['interior_color'] ? __( 'Color', 'printpricepro-bpe' ) : __( 'Black & White', 'printpricepro-bpe' ),
				'cover_color'    => 'color' === $specs['cover_color'] ? __( 'Color', 'printpricepro-bpe' ) : __( 'Black & White', 'printpricepro-bpe' ),
				'binding'        => $binding_data['label'],
				'paper'          => $paper_data['label'],
				'country'        => $specs['country'],
			),
		);
	}
}
