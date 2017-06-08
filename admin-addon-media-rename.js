$(function() {
  ADMIN_ADDON_MEDIA_RENAME.renames = {};

  var clickedEle;
  var modal;
  var $modal;

  // Append modal
  $('body').append(ADMIN_ADDON_MEDIA_RENAME.MODAL);

  $(document).off('click', '[data-dz-name]');
  $(document).on('click', '[data-dz-name]', function(e) {
    clickedEle = $(this);
    modal = $.remodal.lookup[$('[data-remodal-id=modal-admin-addon-media-rename]').data('remodal')];
    $modal = modal.$modal;
    modal.open();

    // Populate fields
    var fileName = clickedEle.text();
    var name = fileName.substr(0, fileName.lastIndexOf('.'));
    var ext = fileName.substr(fileName.lastIndexOf('.') + 1);
    $('[name=old_name]', $modal).val(name);
    $('[name=old_ext]', $modal).val(ext);
    $('[name=new_name]', $modal).val(name);
    $('[name=new_ext]', $modal).val(ext);

    // Reset loading state
    $('.loading', $modal).addClass('hidden');
    $('.button', $modal).removeClass('hidden').css('visibility', 'hidden');
  });

  $(document).on('keyup', '[data-remodal-id=modal-admin-addon-media-rename] input', function(e) {
    var button = $('.button', $modal);
    var fields = ['name', 'ext'];

    var diff = false;
    fields.forEach(function(v) {
      var val1 = $('[name=new_'+v+']', $modal).val();
      var val2 = $('[name=old_'+v+']', $modal).val();

      if (val1 !== val2) {
        diff = true;
        return false;
      }
    });

    button.css('visibility', (diff) ? 'visible' : 'hidden');
  });

  $(document).on('click', '[data-remodal-id=modal-admin-addon-media-rename] .button', function(e) {
    $('.loading', $modal).removeClass('hidden');
    $('.button', $modal).addClass('hidden');
    
    var oldFileName = $('[name=old_name]', $modal).val() + '.' + $('[name=old_ext]', $modal).val();
    var newFileName = $('[name=new_name]', $modal).val() + '.' + $('[name=new_ext]', $modal).val();
    if (newFileName) {
      // Replace occurences in the editor
      var replaceInContent = $('[name=replace]:checked', $modal).val();
      if (replaceInContent == '1') {
        var editor = Grav.default.Forms.Fields.EditorField.Instance.editors.data('codemirror').doc;
        var re = /(\[[^\]]{0,}\])\(([^\)]{0,})\)/g;
        var res;
        var val = editor.getValue();
        var newVal = val;
        while ((res = re.exec(val)) !== null) {
          if (res[2] === oldFileName) {
            newVal = newVal.replace(res[0], res[1] + '(' + newFileName + ')');
          }
        }
        editor.setValue(newVal);
      }

      // Do request
      var replaceAll = $('[name=replace_all]:checked', $modal).val();
      var data = new FormData();
      data.append('media_path', clickedEle.closest('[data-media-local]').attr('data-media-local'));
      data.append('file_name', clickedEle.text());
      data.append('new_file_name', newFileName);
      data.append('replace_all', replaceAll);

      fetch(ADMIN_ADDON_MEDIA_RENAME.PATH, { method: 'POST', body: data, credentials: 'same-origin' })
        .then(res => res.json())
        .then(result => {
          if (result.error) {
            var alertModal = $.remodal.lookup[$('[data-remodal-id=modal-admin-addon-media-rename-alert]').data('remodal')];
            alertModal.open();
            $('p', alertModal.$modal).html(result.error.msg);
            return;
          }

          clickedEle.text(newFileName);
          modal.close();

          ADMIN_ADDON_MEDIA_RENAME.renames[data.get('file_name')] = newFileName;
        });
    }
  });
});

/**
 * Whenever a 'delmedia' task is called
 * via Fetch API, we intercept the requset
 * and we replace the old file name
 * with the new file name.
 */
(function(fetch) {
  window.fetch = function() {
    if (arguments[0].indexOf('delmedia') !== -1) {
      var data = arguments[1];
      var newName = ADMIN_ADDON_MEDIA_RENAME.renames[data.body.get('filename')];
      if (typeof newName !== 'undefined') {
        data.body.set('filename', newName);
      }
    }
    return fetch.apply(this, arguments);
  };

})(window.fetch);