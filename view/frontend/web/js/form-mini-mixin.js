/**
 * Renders product/category rows (image, price) in the core quick search dropdown
 * and opens their page directly instead of searching for their name.
 */
define(['jquery'], function ($) {
    'use strict';

    return function (widget) {
        $.widget('mage.quickSearch', widget, {
            options: {
                template:
                    '<li class="<%- data.row_class %>" id="qs-option-<%- data.index %>" role="option"' +
                    '<% if (data.url) { %> data-url="<%- data.url %>"<% } %>>' +
                    '<% if (data.image) { %><img class="qs-option-image" src="<%- data.image %>" alt="" ' +
                    'width="40" height="40" loading="lazy"><% } %>' +
                    '<span class="qs-option-name"><%- data.title %></span>' +
                    '<span aria-hidden="true" class="amount"><%- data.num_results || data.label || "" %></span>' +
                    '</li>'
            },

            _onSubmit: function (e) {
                var url = this.responseList.selected && this.responseList.selected.closest('[data-url]').data('url');

                if (url) {
                    e.preventDefault();
                    window.location.href = url;

                    return;
                }

                return this._super(e);
            }
        });

        return $.mage.quickSearch;
    };
});
