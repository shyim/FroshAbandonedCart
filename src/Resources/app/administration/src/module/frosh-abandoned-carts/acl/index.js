Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'additional_permissions',
    parent: null,
    key: 'frosh_abandoned_carts',
    roles: {
        frosh_abandoned_carts: {
            privileges: [
                'frosh_abandoned_carts:read',
                'frosh_abandoned_cart:read',
                'frosh_abandoned_cart_line_item:read',
            ],
            dependencies: [],
        },
    },
});
