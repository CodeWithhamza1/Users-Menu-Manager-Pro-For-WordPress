/**
 * Users Menu Manager Pro - Admin JavaScript
 */

(function($) {
    'use strict';

    var UMMPAdmin = {
        init: function() {
            this.bindEvents();
            this.initSortable();
        },

        bindEvents: function() {
            // Role selection change
            $('#selected_role').on('change', this.handleRoleChange);
            
            // Save menu restrictions
            $('#save_menu_restrictions').on('click', this.saveMenuRestrictions);
            
            // Reset menu restrictions
            $('#reset_menu_restrictions').on('click', this.resetMenuRestrictions);
        },

        handleRoleChange: function() {
            var selectedRole = $(this).val();
            
            if (!selectedRole) {
                $('#menu_configuration').hide();
                $('#menu_preview').html('<p>Select a role to preview the menu structure.</p>');
                return;
            }

            // Show configuration area
            $('#menu_configuration').show();
            
            // Load menu structure for selected role
            $.ajax({
                url: ummp_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ummp_get_menu_structure',
                    role: selectedRole,
                    nonce: ummp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        UMMPAdmin.renderMenuStructure(response.data, selectedRole);
                        UMMPAdmin.renderMenuPreview(response.data, selectedRole);
                    } else {
                        console.error('Failed to load menu structure:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                }
            });
        },

        renderMenuStructure: function(menuStructure, role) {
            var availableMenus = $('#available_menus');
            var hiddenMenus = $('#hidden_menus');
            
            // Clear existing content
            availableMenus.empty();
            hiddenMenus.empty();
            
            // Render menu items
            menuStructure.forEach(function(menuItem) {
                var menuHtml = '<div class="ummp-menu-item" data-slug="' + menuItem.slug + '">' +
                    '<span class="dashicons ' + menuItem.icon + '"></span>' +
                    '<span class="menu-title">' + menuItem.title + '</span>' +
                    '</div>';
                
                if (menuItem.hidden) {
                    hiddenMenus.append(menuHtml);
                } else {
                    availableMenus.append(menuHtml);
                }
            });
            
            // Reinitialize sortable
            this.initSortable();
        },

        renderMenuPreview: function(menuStructure, role) {
            var preview = $('#menu_preview');
            var roleName = $('option[value="' + role + '"]').text();
            
            var previewHtml = '<div class="ummp-menu-preview-content">' +
                '<div class="ummp-menu-preview-role">' +
                '<h4>Menu Preview for: ' + roleName + '</h4>' +
                '</div>' +
                '<div class="ummp-menu-preview-list">';
            
            menuStructure.forEach(function(menuItem) {
                var itemClass = menuItem.hidden ? 'ummp-menu-preview-item hidden' : 'ummp-menu-preview-item';
                previewHtml += '<div class="' + itemClass + '">' +
                    '<span class="dashicons ' + menuItem.icon + '"></span>' +
                    '<span class="menu-title">' + menuItem.title + '</span>' +
                    '</div>';
            });
            
            previewHtml += '</div></div>';
            preview.html(previewHtml);
        },

        saveMenuRestrictions: function() {
            var selectedRole = $('#selected_role').val();
            var hiddenMenus = [];
            
            // Get hidden menus from the hidden column
            $('#hidden_menus .ummp-menu-item').each(function() {
                hiddenMenus.push($(this).data('slug'));
            });
            
            // Save via AJAX
            $.ajax({
                url: ummp_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ummp_save_menu_restrictions',
                    role: selectedRole,
                    hidden_menus: hiddenMenus,
                    nonce: ummp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        UMMPAdmin.showMessage('Menu restrictions saved successfully!', 'success');
                        
                        // Update preview
                        UMMPAdmin.renderMenuPreview(
                            UMMPAdmin.getCurrentMenuStructure(),
                            selectedRole
                        );
                    } else {
                        UMMPAdmin.showMessage('Failed to save menu restrictions: ' + response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    UMMPAdmin.showMessage('Error saving menu restrictions: ' + error, 'error');
                }
            });
        },

        resetMenuRestrictions: function() {
            var selectedRole = $('#selected_role').val();
            
            if (!selectedRole) {
                return;
            }
            
            // Reset via AJAX
            $.ajax({
                url: ummp_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ummp_reset_menu_restrictions',
                    role: selectedRole,
                    nonce: ummp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload the menu structure
                        $('#selected_role').trigger('change');
                        UMMPAdmin.showMessage('Menu restrictions reset to defaults!', 'success');
                    } else {
                        UMMPAdmin.showMessage('Failed to reset menu restrictions: ' + response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    UMMPAdmin.showMessage('Error resetting menu restrictions: ' + error, 'error');
                }
            });
        },

        getCurrentMenuStructure: function() {
            var structure = [];
            
            // Get available menus
            $('#available_menus .ummp-menu-item').each(function() {
                structure.push({
                    slug: $(this).data('slug'),
                    title: $(this).find('.menu-title').text(),
                    icon: $(this).find('.dashicons').attr('class').replace('dashicons ', ''),
                    hidden: false
                });
            });
            
            // Get hidden menus
            $('#hidden_menus .ummp-menu-item').each(function() {
                structure.push({
                    slug: $(this).data('slug'),
                    title: $(this).find('.menu-title').text(),
                    icon: $(this).find('.dashicons').attr('class').replace('dashicons ', ''),
                    hidden: true
                });
            });
            
            return structure;
        },

        initSortable: function() {
            // Make available menus sortable
            $('#available_menus').sortable({
                connectWith: '#hidden_menus',
                placeholder: 'ui-sortable-placeholder',
                helper: 'clone',
                start: function(e, ui) {
                    ui.placeholder.height(ui.item.height());
                }
            });
            
            // Make hidden menus sortable
            $('#hidden_menus').sortable({
                connectWith: '#available_menus',
                placeholder: 'ui-sortable-placeholder',
                helper: 'clone',
                start: function(e, ui) {
                    ui.placeholder.height(ui.item.height());
                }
            });
        },

        showMessage: function(message, type) {
            // Remove existing messages
            $('.ummp-message').remove();
            
            var messageClass = type === 'success' ? 'notice notice-success' : 'notice notice-error';
            var messageHtml = '<div class="ummp-message ' + messageClass + ' is-dismissible">' +
                '<p>' + message + '</p>' +
                '<button type="button" class="notice-dismiss">' +
                '<span class="screen-reader-text">Dismiss this notice.</span>' +
                '</button>' +
                '</div>';
            
            $('.ummp-admin h1').after(messageHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $('.ummp-message').fadeOut();
            }, 5000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        UMMPAdmin.init();
    });

})(jQuery);
