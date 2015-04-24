jQuery(document).ready(function ($) {
    kokagrams_open_pointer(0);

    function kokagrams_open_pointer(i) {
        pointer = kokagramsPointer.pointers[i];
        options = $.extend(pointer.options, {
            close: function () {
                $.post(ajaxurl, {
                    pointer: pointer.pointer_id,
                    action: 'dismiss-wp-pointer'
                });
            }
        });

        $(pointer.target).pointer(options).pointer('open');
    }
});