<?php
defined('_JEXEC') or die;

// Optional prefill parameters from the query string (used by the "share link" feature).
$app   = JFactory::getApplication();
$text  = $app->input->getString('text', '');
$lang1 = $app->input->getCmd('lang', '');
$lang2 = $app->input->getCmd('lang2', '');

// Restrict the direction parameters to the known, safe variants; default to Cyrillic -> Latin.
$variants = array('crh-cyrl', 'crh-latn');
$from = in_array($lang1, $variants, true) ? $lang1 : 'crh-cyrl';
$to   = in_array($lang2, $variants, true) ? $lang2 : 'crh-latn';
?>
<div class="module-heading">
    <h2><?php echo JText::_('MOD_TITLE'); ?></h2>
    <p><?php echo JText::_('MOD_DESCRIPTION'); ?></p>
</div>
<div class="translit-main">
    <div class="tab">
        <button class="tablinks active tr-inline"  onclick="openTab(event, 'tr_inline')"><i class="fa fa-pencil-square-o fa-lg"></i> <?php echo JText::_('MOD_INLINE_MODE'); ?></button>
        <button class="tablinks tr-file" onclick="openTab(event, 'tr_file')"><i class=" fa fa-file-text-o fa-lg"></i> <?php echo JText::_('MOD_FILE_MODE'); ?></button>
    </div>

    <!-- Tab content -->
    <div id="tr_inline" style="display: block" class="tabcontent">
        <div class="g-grid">
            <div class="g-block size-46">
                <select class="select" id="inline_select_from">
                    <option value="crh-cyrl" <?php echo ($from === 'crh-cyrl') ? 'selected' : ''; ?>><?php echo JText::_('MOD_CYRILLIC'); ?></option>
                    <option value="crh-latn" <?php echo ($from === 'crh-latn') ? 'selected' : ''; ?>><?php echo JText::_('MOD_LATIN'); ?></option>
                </select>
                <div class="grow-wrap">
                    <textarea id="translit_inp" placeholder="<?php echo JText::_('MOD_TEXT_PLACEHOLDER'); ?>"><?php echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <a class="custom-to-clip3" title="<?php echo JText::_('MOD_A_TITLE3'); ?>"><i onclick="saveLink()" class="fa fa-link fa-lg"></i></a>
                    <a class="custom-to-clip2" title="<?php echo JText::_('MOD_A_TITLE2'); ?>"><i class="fa fa-expand fa-lg"></i></a>
                    <a class="custom-to-clip"  title="<?php echo JText::_('MOD_A_TITLE1'); ?>"><i id="toggleKeyboard" class="fa fa-keyboard fa-lg"></i></a>
                    <i class="char-counter"></i>
                    <a class="clear" title="<?php echo JText::_('MOD_A_TITLE4'); ?>"><i class="fa fa-close  fa-lg"></i></a>
                </div>
            </div>
            <div class="g-block size-8" style="text-align: center"><a id="switch_langs" title="<?php echo JText::_('MOD_SWAP_DIRECTION'); ?>"><i class="fa fa-exchange fa-lg"></i></a></div>
            <div class="g-block size-46">
                <select class="select" id="inline_select_to">
                    <option value="crh-cyrl" <?php echo ($to === 'crh-cyrl') ? 'selected' : ''; ?>><?php echo JText::_('MOD_CYRILLIC'); ?></option>
                    <option value="crh-latn" <?php echo ($to === 'crh-latn') ? 'selected' : ''; ?>><?php echo JText::_('MOD_LATIN'); ?></option>
                </select>
                <div class="grow-wrap">
                    <textarea id="translit_out" placeholder="<?php echo JText::_('MOD_TEXT_PLACEHOLDER2'); ?>"  autosize></textarea>
                    <a class="copy-to-clip" title="<?php echo JText::_('MOD_A_TITLE5'); ?>"><i class="fa fa-copy fa-lg"></i></a>
                </div>
            </div>
        </div>


    </div>

    <div id="tr_file" class="tabcontent">
        <form enctype="multipart/form-data" action="upload.php" method="post">
            <div class="drop-files">
                <input type="file" multiple name="file[]" id="file" onchange="updateList()" accept=".docx, .txt"/>
                <span><i class="fa fa-upload fa-lg"></i> <?php echo JText::_('MOD_CHOOSE_FILES'); ?> (.docx, .txt) (> 100MB)</span>
                <p style="margin: 0 0 10px; text-align: center; font-size: 12px;"><?php echo JText::_('MOD_CHOOSE_FILES_DESCR'); ?></p>
            </div>

            <div id="fileList"></div>

            <div id="uploadControl" style="display: none">
                <hr>
                <div>
                    <label for="file_select_to"><?php echo JText::_('MOD_TRANSLATE_TO'); ?> </label>
                    <select class="select" id="file_select_to">
                        <option value="crh-cyrl" ><?php echo JText::_('MOD_CYRILLIC'); ?></option>
                        <option value="crh-latn" selected><?php echo JText::_('MOD_LATIN'); ?></option>
                    </select>
                </div>
                <div style="text-align: center;  padding: 10px;">
                    <button id="transliterate_file" ><i class="fa fa-check fa-lg"></i> <?php echo JText::_('MOD_TRANSLITE_GO'); ?></button>
                    <div class="progress-container"  style="display: none; text-align: center">
                        <p class="progress-title">0%</p>
                        <progress id="loading" value="0" max="100"> 32% </progress>
                    </div>
                </div>

            </div>
            <div id="file_zip" style="display: none">
                <hr>
                <div>
                    <h4><?php echo JText::_('MOD_SUCCESS_TITLE'); ?></h4>
                    <h6><?php echo JText::_('MOD_SUCCESS_DESCRIPTION'); ?></h6>
                </div>
                <div class="link-container">

                </div>
                <a class="retry"><?php echo JText::_('MOD_TRY_ANOTHER'); ?></a>
            </div>
            <div id="error" style="display: none">
                <hr>
                <div class="link-container">

                </div>
                <a class="retry"><?php echo JText::_('MOD_TRY_ANOTHER'); ?></a>
            </div>

        </form>
        <h4 style="margin: 0"><?php echo JText::_('MOD_TIP_HEADER'); ?></h4>
        <ul style="margin: 0 15px;">
            <li>
                <p style="font-size: 12px; margin: 0"><?php echo JText::_('MOD_TIP_1'); ?></p>
            </li>
            <li>
                <p style="font-size: 12px; margin: 0"><?php echo JText::_('MOD_TIP_2'); ?></p>
            </li>
            <li>
                <p style="font-size: 12px; margin: 0"><?php echo JText::_('MOD_TIP_3'); ?></p>
            </li>
        </ul>
    </div>



</div>
<div class="keyboard-container" id="keyboard">
    <div class="keyboard-header">keyboard</div>
    <span class="close-btn" id="close">✖</span>
    <button class="special" id="switchLang">🌍</button>

    <div class="keyboard-layout" id="latin">
        <div class="keyboard-row">
            <button class="key">Q</button>
            <button class="key">W</button>
            <button class="key">E</button>
            <button class="key">R</button>
            <button class="key">T</button>
            <button class="key">Y</button>
            <button class="key">U</button>
            <button class="key">I</button>
            <button class="key">O</button>
            <button class="key">P</button>
            <button class="key">Ğ</button>
            <button class="key">Ş</button>
        </div>
        <div class="keyboard-row">
            <button class="caps">Caps Lock</button>
            <button class="key">A</button>
            <button class="key">S</button>
            <button class="key">D</button>
            <button class="key">F</button>
            <button class="key">G</button>
            <button class="key">H</button>
            <button class="key">J</button>
            <button class="key">K</button>
            <button class="key">L</button>
            <button class="key">Ç</button>
            <button class="key">Ñ</button>
            <button class="delete">⌫</button>
        </div>
        <div class="keyboard-row">
            <button class="shift">Shift</button>
            <button class="key">Z</button>
            <button class="key">X</button>
            <button class="key">C</button>
            <button class="key">V</button>
            <button class="key">B</button>
            <button class="key">N</button>
            <button class="key">M</button>
            <button class="key">Ö</button>
            <button class="key">Ü</button>
            <button class="space"><?php echo JText::_('MOD_KEY_SPACE'); ?></button>
        </div>
    </div>

    <div class="keyboard-layout" id="cyrillic" style="display: none;">
        <div class="keyboard-row">
            <button class="key">Й</button>
            <button class="key">Ц</button>
            <button class="key">У</button>
            <button class="key">К</button>
            <button class="key">Е</button>
            <button class="key">Н</button>
            <button class="key">Г</button>
            <button class="key">Ш</button>
            <button class="key">Щ</button>
            <button class="key">З</button>
            <button class="key">Ҝ</button>
            <button class="key">Ҫ</button>
        </div>
        <div class="keyboard-row">
            <button class="caps">Caps Lock</button>
            <button class="key">Ф</button>
            <button class="key">Ы</button>
            <button class="key">В</button>
            <button class="key">А</button>
            <button class="key">П</button>
            <button class="key">Р</button>
            <button class="key">О</button>
            <button class="key">Л</button>
            <button class="delete">⌫</button>
        </div>
        <div class="keyboard-row">
            <button class="shift">Shift</button>
            <button class="key">Я</button>
            <button class="key">Ч</button>
            <button class="key">С</button>
            <button class="key">М</button>
            <button class="key">И</button>
            <button class="key">Т</button>
            <button class="key">Ь</button>
            <button class="key">Б</button>
            <button class="key">Ю</button>
            <button class="space"><?php echo JText::_('MOD_KEY_SPACE'); ?></button>
        </div>
    </div>
</div>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script>
    var totalsize = 0;
    var currentStep = 0;

    function init(){
        initControls()
    }
    function initControls(){
        jQuery('#translit_inp').on('input', (e) => { return transliterate() });
        jQuery('#transliterate_file').on('click', (e) => { uploadFiles(e) });
        jQuery('.retry').on('click', () => {
            jQuery('#file_zip').hide();
            jQuery('#fileList').html('')
            jQuery('#file').val('')


        })
        jQuery('#inline_select_from').on('change', (e) => {
            var value = jQuery(e.target).val();
            if(value == 'crh-cyrl') {
                jQuery('#inline_select_to').val('crh-latn')
            } else {
                jQuery('#inline_select_to').val('crh-cyrl')
            }
            transliterate(true)
        })
        jQuery('#inline_select_to').on('change', (e) => {
            var value = jQuery(e.target).val();
            if(value == 'crh-cyrl') {
                jQuery('#inline_select_from').val('crh-latn')
            } else {
                jQuery('#inline_select_from').val('crh-cyrl')
            }
            transliterate(true)
        })
        jQuery('#switch_langs').on('click', (e) => {
            if(jQuery('#inline_select_from').val() == 'crh-cyrl') {
                jQuery('#inline_select_from').val('crh-latn')
                jQuery('#inline_select_to').val('crh-cyrl')
            } else {
                jQuery('#inline_select_from').val('crh-cyrl')
                jQuery('#inline_select_to').val('crh-latn')
            }
            transliterate(true)
        })
        jQuery('.copy-to-clip').on('click', (e) => { copyToClipboard(e) });
        jQuery('.clear').on('click', (e) => { jQuery(e.target).closest('.grow-wrap').find('textarea').val('');jQuery("#translit_out").val(""); transliterate() });

    }

    function transliterate(ignore = false) {
        var value = jQuery('#translit_inp').val()
        if(!ignore){
            if(value.length > 0){
                var firstLetter = Array.from(value)[0];
                if(firstLetter.search(/[а-яА-ЯёЁ]/i) > -1){
                    jQuery('#inline_select_from').val('crh-cyrl')
                    jQuery('#inline_select_to').val('crh-latn')
                } else {
                    jQuery('#inline_select_from').val('crh-latn')
                    jQuery('#inline_select_to').val('crh-cyrl')
                }
            }
        }

        var letterCount = value.replace(/\s+/g, '').length;
        const params = new URLSearchParams(window.location.search);
        params.set("text", jQuery('#translit_inp').val());
        params.set("lang",jQuery('#inline_select_from').val() );
        params.set("lang2",jQuery('#inline_select_to').val() );
        window.history.replaceState({}, "", `${window.location.pathname}?${params.toString()}`);
        jQuery('.char-counter').html(letterCount+"/5000 <?php echo JText::_('MOD_CHARS'); ?>");
        jQuery.ajax({
            url: "/index.php?option=com_ajax&module=translit&method=transliterate&format=json",
            type: "POST",
            data: {text: value, toVariant: jQuery('#inline_select_to').val()},
            success: function (response){
                jQuery('#translit_out').val(response.data.text);
                return true
            }
        });
    }
    function transliterate2(ignore = false) {
        var value = jQuery('#fullscreenInput').val()
        if(!ignore){
            if(value.length > 0){
                var firstLetter = Array.from(value)[0];
                if(firstLetter.search(/[а-яА-ЯёЁ]/i) > -1){
                    jQuery('#inline_select_from').val('crh-cyrl')
                    jQuery('#inline_select_to').val('crh-latn')
                } else {
                    jQuery('#inline_select_from').val('crh-latn')
                    jQuery('#inline_select_to').val('crh-cyrl')
                }
            }
        }

        var letterCount = value.replace(/\s+/g, '').length;
        const params = new URLSearchParams(window.location.search);
        params.set("text", jQuery('#fullscreenInput').val());
        params.set("lang",jQuery('#inline_select_from').val() );
        params.set("lang2",jQuery('#inline_select_to').val() );
        window.history.replaceState({}, "", `${window.location.pathname}?${params.toString()}`);
        jQuery('.char-counter').html(letterCount+"/5000 <?php echo JText::_('MOD_CHARS'); ?>");
        jQuery.ajax({
            url: "/index.php?option=com_ajax&module=translit&method=transliterate&format=json",
            type: "POST",
            data: {text: value, toVariant: jQuery('#inline_select_to').val()},
            success: function (response){
                jQuery('#fullscreenOutput').val(response.data.text);
                return true
            }
        });
    }

    function updateTimer(part){
        if(totalsize == 0){
            return;
        }
        jQuery('.progress-container').show()
        var new_value = (part)*100/totalsize;
        if(new_value > 98) new_value = 98;
        jQuery('.progress-container progress').val(Math.ceil(new_value))
        jQuery('.progress-container .progress-title').html('<i class="fa fa-spin fa-spinner fa-lg"></i> '+Math.ceil(new_value)+'%')
    }
    function uploadFiles(e){
        e.preventDefault();

        jQuery('#transliterate_file').hide()
        var formData = new FormData($(e.target).parents('form')[0]);
        $.ajax({
            url: "/index.php?option=com_ajax&module=translit&method=uploadFiles&format=json",
            type: 'POST',
            success: function (response) {
                if(response.data){
                    jQuery('#fileList').show()
                    transliterateUploaded(response.data, 0);
                }
            },
            error: function(){
                jQuery('.progress-container progress').val(0)
                jQuery('.progress-container .progress-title').html('0%')
                jQuery('.progress-container').hide()
            },
            data: formData,
            cache: false,
            contentType: false,
            processData: false
        });
    }
    function transliterateUploaded(hash, part) {
        updateTimer(part)
        jQuery('.drop-files').hide()
        jQuery('#fileList').hide()
        jQuery('#file').val('')
        jQuery('#transliterate_file i').attr('class', 'fa fa-spin fa-spinner fa-lg');

        jQuery.ajax({
            url: "/index.php?option=com_ajax&module=translit&method=transliterateUploaded&format=json",
            type: "POST",
            data: {hash: hash, toVariant: jQuery('#file_select_to').val(), part: part},
            error: function (e, response) {
                jQuery('#transliterate_file').show()
                jQuery('#transliterate_file i').attr('class', 'fa fa-check fa-lg');
                jQuery('#error').show()
                if(e.responseJSON && e.responseJSON.message){
                    jQuery('#error .link-container').html(e.responseJSON.message);
                } else {
                    jQuery('#error .link-container').html("<?php echo JText::_('MOD_ERROR_TOO_BIG'); ?>");
                }
                jQuery('.drop-files').show()
                jQuery('#uploadControl').hide()
                jQuery('.progress-container progress').val(0)
                jQuery('.progress-container .progress-title').html('0%')
                jQuery('.progress-container').hide()
            },
            complete: function (response){
                if(response.data){
                    if(response.data.total_parts){
                        totalsize = response.data.total_parts
                    }
                    if(response.data.is_finished == false){
                        part++;
                        return transliterateUploaded(hash, part)
                    }
                    jQuery('#transliterate_file').show()
                    jQuery('#transliterate_file i').attr('class', 'fa fa-check fa-lg');
                    jQuery('#error').show()
                    if(e.responseJSON && e.responseJSON.message){
                        jQuery('#error .link-container').html(e.responseJSON.message);
                    } else {
                        jQuery('#error .link-container').html("<?php echo JText::_('MOD_ERROR_TOO_BIG'); ?>");
                    }
                    jQuery('.drop-files').show()
                    jQuery('#uploadControl').hide()
                    jQuery('.progress-container progress').val(0)
                    jQuery('.progress-container .progress-title').html('0%')
                    jQuery('.progress-container').hide()
                }
            },
            success: function (response){
                if(response.data == 'error:too_large'){
                    jQuery('#transliterate_file').show()
                    jQuery('#transliterate_file i').attr('class', 'fa fa-check fa-lg');
                    jQuery('#error').show()
                    jQuery('#error .link-container').html("<?php echo JText::_('MOD_ERROR_TOO_BIG'); ?>");
                    jQuery('.drop-files').show()
                    jQuery('#uploadControl').hide()
                    jQuery('.progress-container progress').val(0)
                    jQuery('.progress-container .progress-title').html('0%')
                    jQuery('.progress-container').hide()
                    return;
                }
                if(response.data){
                    if(response.data.total_parts){
                        totalsize = response.data.total_parts
                    }
                    if(response.data.is_finished == false){
                        part++;
                        return transliterateUploaded(hash, part)
                    }
                    jQuery('#error').hide()
                    jQuery('#transliterate_file').show()
                    jQuery('#transliterate_file i').attr('class', 'fa fa-check fa-lg');
                    jQuery('#file_zip .link-container').html("<a href='"+response.data.result+"' target='_blank'><i class='fa fa-download'></i> <?php echo JText::_('MOD_DOWNLOAD'); ?></a>");
                    jQuery('#file_zip').show()
                    jQuery('.drop-files').show()
                    jQuery('#uploadControl').hide()
                    jQuery('.progress-container progress').val(0)
                    jQuery('.progress-container .progress-title').html('0%')
                    jQuery('.progress-container').hide()
                }
            }
        });
    }

    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tabcontent");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        tablinks = document.getElementsByClassName("tablinks");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";
    }
    updateList = function() {
        jQuery('#file_zip').hide();
        jQuery('#file_zip .link-container').html("");
        jQuery('#error').hide()
        var input = document.getElementById('file');
        var output = document.getElementById('fileList');
        var children = "";
        for (var i = 0; i < input.files.length; ++i) {
            children += '<li> <i class="fa fa-file-alt"></i> <b>' + input.files.item(i).name + '</b></li>';
        }
        output.innerHTML = '<ul>'+children+'</ul>';
        if(input.files.length > 0){
            jQuery('#uploadControl').show()
        } else {
            jQuery('#uploadControl').hide()
        }
    }
    function copyToClipboard (e) {
        // Get the text field

        var copyText = jQuery(e.target).closest('.grow-wrap').find('textarea').val()


        // Copy the text inside the text field
        navigator.clipboard.writeText(copyText);

    }
    jQuery( document ).ready(function() {
        init()
    });

</script>
<style>
    .keyboard-container {
        display: none;
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: #f1f1f1;
        padding: 10px;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        border: 1px solid #ccc;
    }
    .keyboard-row {
        display: flex;
        justify-content: center;
    }
    .key {
        padding: 10px;
        margin: 3px;
        font-size: 16px;
        border: 1px solid #bbb;
        background: #fff;
        cursor: pointer;
        border-radius: 3px;
        text-align: center;
        width: 40px;
        height: 40px;
    }
    .key:hover {
        background: #ddd;
    }
    .special {
        background: #e0e0e0;
        width: 60px;
    }
    .wide {
        width: 100px;
    }
    .close-btn {
        position: absolute;
        top: 5px;
        right: 10px;
        cursor: pointer;
        font-size: 18px;
    }
    .keyboard-header {
        text-align: left;
        font-size: 16px;
        padding: 5px;
    }
    .space {
        flex-grow: 2;
        min-width: 150px;
    }

    .delete {
        background: #ff5c5c;
        color: white;
    }

    .delete:hover {
        background: #e04e4e;
    }

    .caps, .shift, .symbols {
        background: #4CAF50;
        color: white;
    }

    .caps:hover, .shift:hover, .symbols:hover {
        background: #45a049;
    }

    .switch {
        margin-top: 15px;
        padding: 10px 20px;
        font-size: 18px;
        border: none;
        background: #007BFF;
        color: white;
        border-radius: 5px;
        cursor: pointer;
    }

    .switch:hover {
        background: #0056b3;
    }
    .translit-page #g-features{
        background: #f4f5f7;
        min-height: 70vh;
        padding: 0;
    }
    .translit-page #g-features h2,
    .translit-page #g-features  h4{
        margin-bottom: 5px;
    }
    .translit-page #g-features h2 + p{
        margin-top: 5px;
    }
    .translit-page #g-features  h2{
        line-height: 1.2;
    }
    .translit-page #g-features  h6{
        margin-top: 5px;
    }
    .translit-page #g-features  a{
        cursor: pointer;
    }
    .translit-main{
        background: white;
    }
    /* Style the tab */
    .translit-main .tab {
        overflow: hidden;
        border: 1px solid #ccc;
    }

    /* Style the buttons that are used to open the tab content */
    .translit-main .tab button {
        background-color: inherit;
        float: left;
        border: none;
        outline: none;
        cursor: pointer;
        padding: 14px 16px;
        transition: 0.3s;
        text-transform: none;
    }
    #switch_langs{
        min-height: 40px;
        padding: 12px;
    }
    /* Change background color of buttons on hover */
    .translit-main .tab button:hover {
        background-color: #24a0ce;
    }
    a[title] {
        position: relative;
    }
    a[title]:hover::after {
        content: attr(title);
        position: absolute;
        left: 50%;
        bottom: 120%;
        transform: translateX(-50%);
        background: rgba(0, 0, 0, 0.75);
        color: #fff;
        padding: 5px 10px;
        font-size: 12px;
        border-radius: 4px;
        white-space: nowrap;
        z-index: 999;
    }
    /* Create an active/current tablink class */
    .translit-main .tab button.active {

        background: #24a0ce;
        color: white;
    }
    .grow-wrap{
        position: relative;
        display: flex;
        width: 101%;
    }
    .grow-wrap .copy-to-clip{
        position: absolute;
        bottom: 10px;
        right: 20px;
    }
    .grow-wrap .custom-to-clip{
        position: absolute;
        bottom: 10px;
        left: 20px;
    }
    .grow-wrap .custom-to-clip2{
        position: absolute;
        bottom: 10px;
        left: 60px;
    }
    .grow-wrap .custom-to-clip3{
        position: absolute;
        bottom: 10px;
        left: 100px;
    }
    .grow-wrap .char-counter{
        position: absolute;
        bottom: 10px;
        right: 20px;
        color: gray;
    }
    .grow-wrap .clear{
        position: absolute;
        top: 10px;
        right: 20px;
    }

    .fullscreen-container {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100vh;
        background: white;
        z-index: 9999;
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .fullscreen-container textarea {
        width: 90%;
        height: 40vh;
        font-size: 18px;
        padding: 10px;
        border: 1px solid #ccc;
        resize: none;
    }

    .fullscreen-close {
        position: absolute;
        top: 10px;
        right: 20px;
        font-size: 24px;
        cursor: pointer;
        background: none;
        border: none;
    }


    /* Style the tab content */
    .translit-main .tabcontent {
        display: none;
        padding: 12px ;
        border: 1px solid #ccc;
        border-top: none;
    }
    .translit-main .drop-files{
        display: grid;
        align-items: center;
        background: #f9f9f9;
        position: relative;
        height: 100px;
        border: 1px solid lightgray;
        border-radius: 4px;
    }
    .translit-main .drop-files input{
        opacity: 0;
        position: absolute;
        width: 100%;
        height: 100%;
    }
    .translit-main .drop-files span{
        font-weight: bold;
        text-align: center;
    }
    .translit-main #fileList ul{
        list-style: none;
    }
    .grow-wrap::after {
        /* Note the weird space! Needed to preventy jumpy behavior */
        content: attr(data-replicated-value) " ";

        /* This is how textarea text behaves */
        white-space: pre-wrap;

        /* Hidden from view, clicks, and screen readers */
        visibility: hidden;
    }
    .translit-main textarea{
        min-height: 350px;
        padding-right: 40px !important;
        scrollbar-width: thin;
    }
    .translit-main .g-block{
        padding: 10px;
        display: flex;
        flex-direction: column;
    }
    .translit-main select {
        min-width: 200px;
        height: 40px;
        margin: 10px 0;
        border-width: 2px;
        border-radius: 3px;
        min-height: 40px;
    }
    .translit-main #translit_out{
        background: #f9f9f9;
        height: 100%;
        border: 1px solid lightgray;
        border-radius: 4px;
        padding: 0.375rem 1.25rem;

    }

    .translit-main button{
        padding: 0.55rem 1.45rem;
        border: 1px solid #0f97df;
        background: transparent;
        color: #0f97df;
        font-weight: bold;
        transition: 0.3s all;
        text-transform: uppercase;
    }
    .translit-main button:hover{
        background-color: #0f97df;
        color: white;
    }
    #file_zip{
        text-align: center;
    }
    #file_zip .link-container a{
        padding: 0.55rem 1.45rem;
        border: 1px solid #09aa22;
        background: transparent;
        color: #09aa22;
        font-weight: bold;
        text-transform: uppercase;
        display: inline-block;
    }
    #file_zip .link-container a:hover{
        background-color: #09aa22;
        color: white;
    }
    @media only screen and (max-width: 740px) {
        .translit-page #g-navigation{
            min-height: 80px;
            background: #312f38 !important;
        }
        .translit-main textarea{
            min-height: 250px;
        }
        .translit-page #g-features .g-content{
            margin: 0;
            padding: 0;
        }
        .translit-page .module-heading{
            padding: 1em;
        }
    }
    #error{
        font-weight: bold;
        color: darkred;
    }
</style>
<script>
    let capsLock = false;
    let shiftActive = false;
    document.addEventListener("DOMContentLoaded", () => {
        const keyboard = document.getElementById("keyboard");
        const toggleKeyboard = document.getElementById("toggleKeyboard");
        const switchLang = document.getElementById("switchLang");
        const specialChars = document.getElementById("specialChars");
        const latinLayout = document.getElementById("latin");
        const cyrillicLayout = document.getElementById("cyrillic");
        const closeKeyboard = document.getElementById("close");
        let currentLayout = "latin";

        toggleKeyboard.addEventListener("click", (event) => {
            event.preventDefault();
            keyboard.style.display = (keyboard.style.display === "block") ? "none" : "block";
        });
        closeKeyboard.addEventListener("click", () => {
            keyboard.style.display = "none";
        });

        switchLang.addEventListener("click", () => {
            if (currentLayout === "latin") {
                latinLayout.style.display = "none";
                cyrillicLayout.style.display = "block";
                currentLayout = "cyrillic";
            } else if (currentLayout === "cyrillic") {
                cyrillicLayout.style.display = "none";
                latinLayout.style.display = "block";
                currentLayout = "latin"
            }
        });
    });
    document.querySelectorAll(".key, .space, .delete, .caps, .shift").forEach(button => {

        button.addEventListener("click", () => {
            let input = document.getElementById("translit_inp");
            if (button.classList.contains("delete")) {
                input.value = input.value.slice(0, -1);
            } else if (button.classList.contains("space")) {
                input.value += " ";
            } else if (button.classList.contains("caps")) {
                capsLock = !capsLock;
            } else if (button.classList.contains("shift")) {
                shiftActive = true;
            } else {
                input.value += capsLock || shiftActive ? button.textContent.toUpperCase() : button.textContent.toLowerCase();
                shiftActive = false;
            }
            transliterate()
        });
    });

    function saveLink() {
        let link = window.location.href;
        navigator.clipboard.writeText(link)
            .then(() => alert("<?php echo JText::_('MOD_LINK_COPIED'); ?>"))
            .catch(err => alert("<?php echo JText::_('MOD_COPY_ERROR'); ?> " + err));
    }

    document.addEventListener("DOMContentLoaded", () => {
        const expandButton = document.querySelector(".custom-to-clip2");
        let fullscreenContainer = document.createElement("div");
        fullscreenContainer.classList.add("fullscreen-container");
        fullscreenContainer.innerHTML = `
        <button class="fullscreen-close">✖</button>
        <textarea id="fullscreenInput"></textarea>
        <textarea id="fullscreenOutput" ></textarea>
    `;
        document.body.appendChild(fullscreenContainer);

        const fullscreenInput = document.getElementById("fullscreenInput");
        const fullscreenOutput = document.getElementById("fullscreenOutput");
        const closeFullscreen = document.querySelector(".fullscreen-close");
        expandButton.addEventListener("click", () => {
            let textInput = document.getElementById("translit_inp").value;
            let textOutput = document.getElementById("translit_out").value;
            fullscreenContainer.style.display = "flex";
            fullscreenInput.value = textInput;
            fullscreenOutput.value = textOutput;
        });

        closeFullscreen.addEventListener("click", () => {
            fullscreenContainer.style.display = "none";
            document.getElementById("translit_inp").value = fullscreenInput.value;
            document.getElementById("translit_out").value = fullscreenOutput.value;
        });

        fullscreenInput.addEventListener("input", () => {
            transliterate2();
        });
    });


</script>
<?php
    if(!empty($text)){
        echo "
        <script>
        setTimeout(function() {
            transliterate();
        }, 1000);
        </script>
        ";
    }
?>