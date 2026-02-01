import template from './sw-customer-detail.html.twig';

Shopware.Component.override('sw-customer-detail', {
    template,

    computed: {
        abandonedCartsRoute() {
            return {
                name: 'sw.customer.detail.abandoned_carts',
                params: { id: this.$route.params.id },
            };
        },
    },
});
