/**
 * PAHO Jitsi Televisit - Frontend JavaScript
 * Handles UI interactions for televisit features
 *
 * @package OpenEMR\Modules\PahoJitsiTelevisit
 * @license GNU General Public License 3
 */

(function() {
    'use strict';

    /**
     * Copy text to clipboard
     */
    window.pahoTelevisitCopyLink = function(inputId, successMessage) {
        var input = document.getElementById(inputId);
        if (!input) {
            alert('Error: Field not found');
            return;
        }

        input.select();
        input.setSelectionRange(0, 99999);

        try {
            var success = document.execCommand('copy');
            if (success) {
                if (successMessage) {
                    alert(successMessage);
                }
            } else {
                throw new Error('copy failed');
            }
        } catch (err) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(input.value).then(function() {
                    if (successMessage) {
                        alert(successMessage);
                    }
                }).catch(function() {
                    alert('Error copying. Please copy manually.');
                });
            } else {
                alert('Please copy the link manually');
            }
        }
    };

    /**
     * Toggle embedded Jitsi view
     */
    window.pahoTelevisitToggleEmbed = function(containerId, embedUrl) {
        var container = document.getElementById(containerId);
        var iframe = document.getElementById(containerId.replace('_embed', '_iframe'));
        if (!container || !iframe) {
            return;
        }

        if (container.classList.contains('d-none')) {
            container.classList.remove('d-none');
            iframe.src = embedUrl;
        } else {
            container.classList.add('d-none');
            iframe.src = '';
        }
    };

    /**
     * Initialize televisit features on page load
     */
    document.addEventListener('DOMContentLoaded', function() {
        // Any initialization code can go here
    });

})();

