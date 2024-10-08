var lastImageCtrl;
var lastFileCtrl;

function $_GET(key) {
    var p = window.location.search;

    p = p.match(new RegExp(key + '=([^&=]+)'));

    return p ? p[1] : false;
}

function OpenServerBrowser(url, width, height) {
    var iLeft = (screen.width - width) / 2;
    var iTop = (screen.height - height) / 2;
    var sOptions = 'toolbar=no,status=no,resizable=yes,dependent=yes';

    sOptions += ',width=' + width;
    sOptions += ',height=' + height;
    sOptions += ',left=' + iLeft;
    sOptions += ',top=' + iTop;

    var oWindow = window.open(url, 'FCKBrowseWindow', sOptions);
}

function BrowseServer(ctrl, t) {
    lastImageCtrl = ctrl;

    var w = screen.width * 0.5;
    var h = screen.height * 0.5;

    OpenServerBrowser(manager_url + 'media/browser/mcpuk/browser.php?Type=' + t, w, h);
}

function SetUrlChange(el) {
    if ('createEvent' in document) {
        var evt = document.createEvent('HTMLEvents');
        evt.initEvent('blur', false, true);
        el.dispatchEvent(evt);
    } else {
        el.fireEvent('blur');
    }
}

function SetUrl(url, width, height, alt) {
    if (lastFileCtrl) {
        var c = document.getElementById(lastFileCtrl);
        if (c && c.value != url) {
            c.value = url;
            SetUrlChange(c);
        }
        lastFileCtrl = '';
    } else if (lastImageCtrl) {
        var c = document.getElementById(lastImageCtrl);
        if (c && c.value != url) {
            c.value = url;
            SetUrlChange(c);
        }
        lastImageCtrl = '';
    } else {
        return;
    }
}

function act() {
    var url = manager_url + '?a=' + $_GET('a') + '&id=' + $_GET('id');

    if (jQuery("#search").val() != "") url = url + "&search=" + jQuery("#search").val();
    if (jQuery("#show").val() != "") url = url + "&show=" + jQuery("#show").val();
    if (jQuery("#act").val() != "") url = url + "&act=" + jQuery("#act").val();

    var checks = jQuery(".docid:checked").serialize();

    if (checks) url = url + "&" + checks;

    location.href = url;
}

function set_field_value(tag, value) {
    jQuery("#mainloader").css({ "opacity": 1, "visibility": "initial" });

    tag.parent().find('.output').html('<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>');

    jQuery.post(document.location.protocol + '//' + document.location.host + '/set_field_value',
        {
            "table": tag.data("table"),
            "id": tag.closest("tr").data("id"),
            "parent": $_GET('id'),
            "delimiter": tag.data("delimiter"),
            "elements": tag.data("elements"),
            "field": tag.data("field"),
            "type": tag.data("type"),
            "user_func": tag.data("user_func"),
            "value": value
        },
        function (data) {
            tag.parent().find('.output').html(data);
            idx = 0;

            jQuery('.noimgs').each(function () {
                imgs.push(jQuery(this).data('href'));
            });

            if (imgs.length > 0) {
                set_photo(idx);
            }
        }
    );
}

function strip(html) {
    var tmp = document.createElement("DIV");

    tmp.innerHTML = html;

    return tmp.textContent || tmp.innerText;
}

function truncate(str, maxlength) {
    return (str.length > maxlength) ?
        str.slice(0, maxlength - 3) + "..." : str;
}

function blur_input(el) {
    var val = el.val();

    if (el.attr('type') == 'checkbox') {
        if (el.prop("checked")) val = 1
        else val = 0
    }

    el.closest('.input').hide();
    el.closest('.input').next().show();

    set_field_value(el.parent(), val);

    not_submit = false;
}

function set_photo(idx) {
    jQuery.post(document.location.protocol + '//' + document.location.host + '/generatephpto', { 'img': imgs[idx] },
        function (res) {
            jQuery('.noimgs[data-href="' + imgs[idx] + '"]').replaceWith(res);

            idx = idx + 1;
            if (idx < imgs.length) set_photo(idx);
        }
    );
}

document.mutate.onsubmit = function (event) {
    if (not_submit) event.preventDefault();
}

jQuery(document).ready(function () {
    not_submit = false;
    t = '';
    ta_id = '';
    imgs = [];
    idx = 0;

    jQuery('.noimgs').each(function () {
        imgs.push(jQuery(this).data('href'));
    });
    if (imgs.length > 0) {
        set_photo(idx);
    }

    jQuery(jQuery('h2[data-target="#tabProducts"]').parent()).prepend(jQuery('h2[data-target="#tabProducts"]'));

    jQuery('#table_doc').on(how_click, ".output", function (e) {
        e.preventDefault();
        not_submit = true;

        if (jQuery(this).prev().children('input').hasClass('browser')) {
            var iid = jQuery(this).prev().children('input').data('id');
            var t = jQuery(this).prev().children('input').data('browser')
            jQuery(this).prev().show();
            BrowseServer(iid, t);
            return false;
        }

        if (jQuery(this).prev().children('div').hasClass('rte')) {
            t = jQuery(this).prev().children('div').data('type')
            ta_id = '#' + jQuery(this).prev().children('div').attr('id')
            var data = jQuery(this).prev().data();
            jQuery.post(document.location.protocol + '//' + document.location.host + '/getcontent',
                {
                    "table": data['table'],
                    "id": data['id'],
                    "field": data['field']
                },
                function (res) {

                    jQuery("#rta").html('<textarea id="popup_rich_area">' + res + '</textarea>');
                    if (t == 'rte') tinymce.init(config_tinymce4_custom);

                    jQuery("#popup_rich").show();
                }
            )
            return false;
        }

        jQuery(this).hide();
        jQuery(this).prev().show().children('input, select').focus();
    });

    jQuery('#table_doc').on('change', '.browser', function () {
        blur_input(jQuery(this));
    });

    jQuery('#table_doc').on('keyup', ".input input", function (e) {
        e.preventDefault;
        if (e.keyCode == 13) {
            blur_input(jQuery(this));
        }
    });

    jQuery('#table_doc').on('blur', ".input input,.input select", function (e) {
        blur_input(jQuery(this));
    });


    jQuery("#checkall").change(function () {
        if (jQuery(this).prop("checked")) jQuery(".docid").attr({ "checked": "checked" });
        else jQuery(".docid").removeAttr("checked");
    });

    var config_tinymce4_custom =
    {
        relative_urls: true,
        remove_script_host: true,
        convert_urls: false,
        resize: true,
        height: 400,
        extended_valid_elements: "*",
        selector: "#popup_rich textarea",
        document_base_url: document.location.protocol + '//' + document.location.host
    }

    jQuery("#close").click(function () {
        jQuery("#popup_rich").hide();
    });

    jQuery(".save_content").click(function (e) {
        if (t == 'rte') {
            if (tinymce.activeEditor === null) return;
            var text = tinyMCE.activeEditor.getContent();
            tinyMCE.activeEditor.destroy();
        }
        else var text = jQuery('#popup_rich_area').val();
        set_field_value(jQuery(ta_id).parent(), text);
        jQuery("#popup_rich").hide();
        not_submit = false;
    });

    jQuery('#news_str').click(function () {
        jQuery(this).hide();
        jQuery('#spiner_new_str').show();

        jQuery.post(document.location.protocol + '//' + document.location.host + '/getnewdoc', { 'parent': jQuery(this).data('parent'), 'template': jQuery(this).data('template') },
            function (id) {
                jQuery.post(location.href + '&onlyid=' + id, {},
                    function (res) {
                        if (new_doc == 'down') jQuery('#newstrbutt').before(jQuery(res).find('#getstr'));
                        else jQuery('#newstrbutt').after(jQuery(res).find('#getstr'));
                        jQuery('#spiner_new_str').hide();
                        jQuery('#news_str').show();
                    }
                );
            });
    });
});
