'use strict';

define(
    [
        'underscore',
        'oro/datagrid/abstract-action',
        'pim/common/form-modal-creator'
    ],
    function(_, AbstractAction, formModalCreator) {
        return AbstractAction.extend({
            /**
             * {@inheritdoc}
             */
            execute: function () {
                return formModalCreator.createModal(
                    this.model.get(this.propertyCode),
                    this.fetcher,
                    'modal modal--fullPage modal--alignTop modal--large'
                ).then((modal) => {
                    modal.$el.find('.modal-footer').remove();
                });
            }
        });
    }
);
