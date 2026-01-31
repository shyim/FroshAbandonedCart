import template from './frosh-abandoned-carts-detail.html.twig';

const { Criteria } = Shopware.Data;

Shopware.Component.register('frosh-abandoned-carts-detail', {
    template,

    inject: ['repositoryFactory'],

    props: {
        abandonedCartId: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            abandonedCart: null,
            isLoading: true,
        };
    },

    computed: {
        abandonedCartRepository() {
            return this.repositoryFactory.create('frosh_abandoned_cart');
        },

        criteria() {
            const criteria = new Criteria();
            criteria.addAssociation('customer');
            criteria.addAssociation('salesChannel');
            criteria.addAssociation('lineItems.product');

            return criteria;
        },

        lineItemColumns() {
            return [
                {
                    property: 'label',
                    label: this.$tc('frosh-abandoned-carts.detail.columnProduct'),
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'quantity',
                    label: this.$tc('frosh-abandoned-carts.detail.columnQuantity'),
                    allowResize: true,
                    width: '100px',
                },
                {
                    property: 'unitPrice',
                    label: this.$tc('frosh-abandoned-carts.detail.columnUnitPrice'),
                    allowResize: true,
                    width: '150px',
                },
                {
                    property: 'totalPrice',
                    label: this.$tc('frosh-abandoned-carts.detail.columnTotalPrice'),
                    allowResize: true,
                    width: '150px',
                },
            ];
        },
    },

    created() {
        this.loadAbandonedCart();
    },

    methods: {
        async loadAbandonedCart() {
            this.isLoading = true;

            try {
                this.abandonedCart = await this.abandonedCartRepository.get(
                    this.abandonedCartId,
                    Shopware.Context.api,
                    this.criteria
                );
            } catch (error) {
                console.error('Failed to load abandoned cart:', error);
            } finally {
                this.isLoading = false;
            }
        },

        getCustomerName() {
            if (!this.abandonedCart?.customer) {
                return '-';
            }
            const customer = this.abandonedCart.customer;
            return `${customer.firstName} ${customer.lastName} (${customer.email})`;
        },

        formatPrice(price) {
            const currency = this.abandonedCart?.currencyIsoCode || 'EUR';
            return new Intl.NumberFormat('de-DE', {
                style: 'currency',
                currency: currency,
            }).format(price);
        },

        getProductName(lineItem) {
            if (lineItem.product) {
                return lineItem.product.translated?.name || lineItem.product.name;
            }
            return lineItem.label || this.$tc('frosh-abandoned-carts.detail.unknownProduct');
        },
    },
});
