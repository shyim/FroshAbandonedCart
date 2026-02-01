import template from './frosh-abandoned-carts-settings.html.twig';

Shopware.Component.register('frosh-abandoned-carts-settings', {
    template,

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    methods: {
        onLoadingChanged(loading) {
            this.isLoading = loading;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        async onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            try {
                await this.$refs.systemConfig.saveAll();
                this.isSaveSuccessful = true;
            } catch (error) {
                console.error('Failed to save settings:', error);
            } finally {
                this.isLoading = false;
            }
        },
    },
});
