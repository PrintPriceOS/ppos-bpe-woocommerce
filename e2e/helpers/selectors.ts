export const CALC = {
	root: '#printpricepro-bpe-calculator',
	form: '#ppp-bpe-calc-form',
	bookSize: '#ppp-bpe-book-size',
	pages: '#ppp-bpe-pages',
	copies: '#ppp-bpe-copies',
	interiorColor: (value: string) => `input[name="interior_color"][value="${value}"]`,
	coverColor: (value: string) => `input[name="cover_color"][value="${value}"]`,
	binding: '#ppp-bpe-binding',
	paper: '#ppp-bpe-paper',
	country: '#ppp-bpe-country',
	submit: '#ppp-bpe-calc-submit',
	loading: '#ppp-bpe-calc-loading',
	error: '#ppp-bpe-calc-error',
	results: '#ppp-bpe-calc-results',
	summary: '#ppp-bpe-calc-summary',
	breakdown: '#ppp-bpe-calc-breakdown',
	total: '#ppp-bpe-calc-total',
	addToCart: '#ppp-bpe-add-to-cart',
	cartMessage: '#ppp-bpe-cart-message',
};

export const ADMIN_PAGES = [
	{ slug: 'printpricepro-bpe', name: 'Settings' },
	{ slug: 'printpricepro-bpe-pricing', name: 'Pricing Rules' },
	{ slug: 'printpricepro-bpe-orders', name: 'Production Queue' },
	{ slug: 'printpricepro-bpe-license', name: 'License' },
	{ slug: 'printpricepro-bpe-join-os', name: 'Join PrintPrice OS' },
];
