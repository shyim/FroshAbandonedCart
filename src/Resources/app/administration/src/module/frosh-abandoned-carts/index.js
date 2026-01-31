import './page/frosh-abandoned-carts-list';
import './page/frosh-abandoned-carts-detail';
import './page/frosh-abandoned-carts-statistics';
import './page/frosh-abandoned-carts-automation-list';
import './page/frosh-abandoned-carts-automation-detail';
import './acl';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Module.register('frosh-abandoned-carts', {
    type: 'plugin',
    name: 'frosh-abandoned-carts',
    title: 'frosh-abandoned-carts.general.title',
    description: 'frosh-abandoned-carts.general.description',
    color: '#ff6b35',
    icon: 'regular-shopping-cart',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        list: {
            component: 'frosh-abandoned-carts-list',
            path: 'list',
            meta: {
                privilege: 'frosh_abandoned_carts:read',
            },
        },
        detail: {
            component: 'frosh-abandoned-carts-detail',
            path: 'detail/:id',
            meta: {
                privilege: 'frosh_abandoned_carts:read',
                parentPath: 'frosh.abandoned.carts.list',
            },
            props: {
                default: (route) => ({
                    abandonedCartId: route.params.id,
                }),
            },
        },
        statistics: {
            component: 'frosh-abandoned-carts-statistics',
            path: 'statistics',
            meta: {
                privilege: 'frosh_abandoned_carts:read',
                parentPath: 'frosh.abandoned.carts.list',
            },
        },
        automations: {
            component: 'frosh-abandoned-carts-automation-list',
            path: 'automations',
            meta: {
                privilege: 'frosh_abandoned_carts:read',
                parentPath: 'frosh.abandoned.carts.list',
            },
        },
        'automation.detail': {
            component: 'frosh-abandoned-carts-automation-detail',
            path: 'automations/detail/:id',
            meta: {
                privilege: 'frosh_abandoned_carts:read',
                parentPath: 'frosh.abandoned.carts.automations',
            },
            props: {
                default: (route) => ({
                    automationId: route.params.id,
                }),
            },
        },
        'automation.create': {
            component: 'frosh-abandoned-carts-automation-detail',
            path: 'automations/create',
            meta: {
                privilege: 'frosh_abandoned_carts:read',
                parentPath: 'frosh.abandoned.carts.automations',
            },
        },
    },

    navigation: [
        {
            id: 'frosh-abandoned-carts',
            label: 'frosh-abandoned-carts.general.title',
            color: '#ff6b35',
            icon: 'regular-shopping-cart',
            parent: 'sw-customer',
            position: 100,
            privilege: 'frosh_abandoned_carts:read',
        },
        {
            path: 'frosh.abandoned.carts.list',
            label: 'frosh-abandoned-carts.general.overview',
            color: '#ff6b35',
            parent: 'frosh-abandoned-carts',
            privilege: 'frosh_abandoned_carts:read',
        },
        {
            path: 'frosh.abandoned.carts.statistics',
            label: 'frosh-abandoned-carts.general.statistics',
            color: '#ff6b35',
            parent: 'frosh-abandoned-carts',
            privilege: 'frosh_abandoned_carts:read',
        },
        {
            id: 'frosh-abandoned-carts-automations',
            path: 'frosh.abandoned.carts.automations',
            label: 'frosh-abandoned-carts.automations.title',
            color: '#ff6b35',
            parent: 'frosh-abandoned-carts',
            privilege: 'frosh_abandoned_carts:read',
        },
    ],
});
