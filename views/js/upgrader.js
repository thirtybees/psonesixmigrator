(function () {
  function initializeUpgrader() {
    if (typeof $ === 'undefined' || typeof window.upgrader === 'undefined') {
      setTimeout(initializeUpgrader, 100);

      return;
    }

    function setConfig(key, value, type) {
      $.ajax({
        type: 'POST',
        url: window.upgrader.currentIndex + '&token=' + window.upgrader.token + '&tab=' + window.upgrader.tab + '&ajax=1&action=setConfig',
        async: true,
        dataType: 'json',
        data: {
          configKey: key,
          configValue: value,
          configType: type,
        },
        success: function (response) {
          if (response && response.success) {
            window.showSuccessMessage('Setting updated');
          } else {
            window.showSuccessMessage('Could not update setting');
          }
        },
        error: function () {
          window.showErrorMessage('Could not update setting');
        },
      });
    }

    function ucFirst(str) {
      if (str.length > 0) {
        return str[0].toUpperCase() + str.substring(1);
      }

      return str;
    }

    function updateInfoStep(msg) {
      var $infoStep = $('#infoStep');

      if (msg) {
        $infoStep
          .append(msg + '<div class="clear"></div>')
          .attr({ scrollTop: $infoStep.attr('scrollHeight') }, 1);
      }
    }

    function addError(arrError) {
      var $errorDuringUpgrade = $('#errorDuringUpgrade');
      var $infoError = $('#infoError');
      var i;

      if (typeof arrError !== 'undefined' && arrError.length) {
        $errorDuringUpgrade.show();

        for (i = 0; i < arrError.length; i += 1) {
          $infoError.append(arrError[i] + '<br />');
        }
        // Note : jquery 1.6 make uses of prop() instead of attr()
        $infoError.attr({ scrollTop: $infoError.attr('scrollHeight') }, 1);
      }
    }

    function addQuickInfo(arrQuickInfo) {
      var $quickInfo = $('#quickInfo');
      var i;

      if (arrQuickInfo) {
        $quickInfo.show();
        for (i = 0; i < arrQuickInfo.length; i += 1) {
          $quickInfo.append(arrQuickInfo[i] + '<br />');
        }
        // Note : jquery 1.6 make uses of prop() instead of attr()
        $quickInfo.attr({ scrollTop: $quickInfo.attr('scrollHeight') }, 1);
      }
    }

    window.upgrader.firstTimeParams = window.upgrader.firstTimeParams.nextParams;
    window.upgrader.firstTimeParams.firstTime = '1';

    // js initialization : prepare upgrade and rollback buttons
    function refreshChannelInfo() {
      var channel = $('select[name=channel]').find('option:selected').val();
      var $selectedVersion = $('#selectedVersion');
      var $upgradeNow = $('#upgradeNow');
      var $selectChannelErrors = $('#channelSelectErrors');

      $upgradeNow.attr('disabled', 'disabled');
      $selectedVersion.html('<i class="icon icon-spinner icon-pulse"></i>');
      $selectChannelErrors.hide();

      if (typeof window.upgrader.currentChannelAjax === 'object') {
        window.upgrader.currentChannelAjax.abort();
      }

      window.upgrader.currentChannelAjax = $.ajax({
        type: 'POST',
        url: window.upgrader.ajaxLocation,
        async: true,
        dataType: 'JSON',
        data: {
          dir: window.upgrader.dir,
          token: window.upgrader.token,
          autoupgradeDir: window.upgrader.autoupgradeDir,
          tab: 'AdminThirtyBeesMigrate',
          action: 'getChannelInfo',
          ajax: '1',
          ajaxToken: window.upgrader.ajaxToken,
          params: {
            channel: channel,
          },
        },
        success: function (res) {
          var result;
          if (res && !res.error) {
            result = res.nextParams.result;
            if (!result.available) {
              $selectedVersion.html('Not available');
            } else {
              $selectedVersion.html(result.version);
              $upgradeNow.attr('disabled', false);
            }
            $selectChannelErrors.hide();
          } else if (res.error && res.status) {
            $selectedVersion.html('Error');
            $selectChannelErrors.html('Error during channel selection: ' + res.status);
            $selectChannelErrors.show();
          }
        },
        error: function () {
          $selectedVersion.html('Error');
          $selectChannelErrors.html('Could not connect with the server');
          $selectChannelErrors.show();
        },
      });
    }

    $(document).ready(function () {
      var $channelSelect = $('#channelSelect');
      var $restoreNameSelect = $('select[name=restoreName]');

      $channelSelect.change(function () {
        refreshChannelInfo();
      });
      $('div[id|=for]').hide();
      $('select[name=channel]').change();

      // The configuration forms
      $('.generatedForm select').change(function () {
        setConfig($(this).attr('name'), $(this).val(), 'select');
      });
      // The configuration forms
      $('.generatedForm input[type=radio]').change(function () {
        var value = $(this).val();
        if (value === '0') {
          value = false;
        }
        value = !!value;
        setConfig($(this).attr('name'), value, 'bool');
      });


      // the following prevents to leave the page at the inappropriate time
      $.xhrPool = [];
      $.xhrPool.abortAll = function () {
        $.each(this, function (jqXHR) {
          if (jqXHR && (parseInt(jqXHR.readystate, 10) !== 4)) {
            jqXHR.abort();
          }
        });
      };
      $('.upgradestep').click(function (e) {
        e.preventDefault();
        // $.scrollTo('#options')
      });

      // set timeout to 120 minutes (before aborting an ajax request)
      $.ajaxSetup({ timeout: 7200000 });

      // prepare available button here, without params ?
      prepareNextButton('#upgradeNow', window.upgrader.firstTimeParams);

      /**
       * reset rollbackParams js array (used to init rollback button)
       */
      $restoreNameSelect.change(function () {
        var rollbackParams;

        $(this).next().remove();
        // show delete button if the value is not 0
        if ($(this).val()) {
          $(this).after('<a class="confirmBeforeDelete btn btn-danger" href="index.php?tab=AdminThirtyBeesMigrate&token=' + window.upgrader.token + '&amp;deletebackup&amp;name=' + $(this).val() + '"><i class="icon icon-times"></i> Delete</a>');
          $(this).next().click(function (e) {
            window.swal({
              title: 'Delete backup',
              text: 'Are you sure you want to delete this backup?',
              type: 'warning',
              showCancelButton: true,
            }).then(
              function () {},
              function (dismiss) {
                if (dismiss === 'cancel' || dismiss === 'close') {
                  e.preventDefault();
                }
              }
            );
          });
        }

        if ($restoreNameSelect.val()) {
          $('#rollback').removeAttr('disabled');
          rollbackParams = $.extend(true, {}, window.upgrader.firstTimeParams);

          delete rollbackParams.backupName;
          delete rollbackParams.backupFilesFilename;
          delete rollbackParams.backupDbFilename;
          delete rollbackParams.restoreFilesFilename;
          delete rollbackParams.restoreDbFilenames;

          // init new name to backup
          rollbackParams.restoreName = $restoreNameSelect.val();
          prepareNextButton('#rollback', rollbackParams);
          // Note : theses buttons have been removed.
          // they will be available in a future release (when DEV_MODE and MANUAL_MODE enabled)
          // prepareNextButton('#restoreDb', rollbackParams);
          // prepareNextButton('#restoreFiles', rollbackParams);
        } else {
          $('#rollback').attr('disabled', 'disabled');
        }
      });
    });

    function showConfigResult(msg, type) {
      var $configResult = $('#configResult');
      var showType = null;

      if (type === null) {
        showType = 'conf';
      }
      $configResult.html('<div class="' + showType + '">' + msg + '</div>').show();
      if (type === 'conf') {
        $configResult.delay(3000).fadeOut('slow', function () {
          location.reload();
        });
      }
    }

    function startProcess(type) {
      // hide useless divs, show activity log
      $('#informationBlock,#comparisonBlock, #currentConfigurationBlock, #backupOptionsBlock, #upgradeOptionsBlock, #upgradeButtonBlock, .generatedForm').slideUp('fast');
      $('#activityLogBlock').slideDown();

      $(window).bind('beforeunload', function (e) {
        var event = e;

        if (confirm(window.upgrader.txtError[38])) {
          $.xhrPool.abortAll();
          $(window).unbind('beforeunload');
          return true;
        } else if (type === 'upgrade') {
          event.returnValue = false;
          event.cancelBubble = true;
          if (event.stopPropagation) {
            event.stopPropagation();
          }
          if (event.preventDefault) {
            event.preventDefault();
          }
        }

        return false;
      });
    }

    function afterUpdateConfig(res) {
      var params = res.nextParams;
      var config = params.config;
      var oldChannel = $('select[name=channel] option.current');
      var newChannel = $('select[name=channel] option[value=' + config.channel + ']');
      var $upgradeNow = $('#upgradeNow');

      if (config.channel !== oldChannel.val()) {
        oldChannel.removeClass('current');
        oldChannel.html(oldChannel.html().substr(2));
        newChannel.addClass('current');
        newChannel.html('* ' + newChannel.html());
      }
      if (parseInt(res.error, 10) === 1) {
        showConfigResult(res.nextDesc, 'error');
      } else {
        showConfigResult(res.nextDesc);
      }
      $upgradeNow.unbind();
      $upgradeNow.replaceWith('<a class="button-autoupgrade" href="' + window.upgrader.currentIndex + '&token=' + window.upgrader.token + '">Click to refresh the page and use the new configuration</a>');
    }

    function isAllConditionOk() {
      var isOk = true;

      $('input[name="goToUpgrade[]"]').each(function () {
        if (!($(this).is(':checked'))) {
          isOk = false;
        }
      });

      return isOk;
    }

    function afterUpgradeNow() {
      var $upgradeNow = $('#upgradeNow');

      startProcess('upgrade');
      $upgradeNow.unbind();
      $upgradeNow.replaceWith('<span id="upgradeNow" class="button-autoupgrade">Migrating to thirty bees...</span>');
    }

    function afterUpgradeComplete(res) {
      var params = res.nextParams;
      var $pleaseWait = $('#pleaseWait');
      var $upgradeResultToDoList = $('#upgradeResultToDoList');
      var $upgradeResultCheck = $('#upgradeResultCheck');
      var $infoStep = $('#infoStep');
      var todoList = [
        'Cookies have changed, you will need to log in again once you refreshed the page',
        'Javascript and CSS files have changed, please clear your browser cache with CTRL-F5',
        'Please check that your front-office theme is functional (try to create an account, place an order...',
        'Product images do not appear in the front-office? Try regenerating the thumbnails in Preferences > Images',
        'Do not forget to reactivate your shop once you have checked everything!',
      ];
      var i;
      var todoUl = '<ul>';
      $upgradeResultToDoList
        .addClass('hint clear')
        .html('<h3>ToDo list:</h3>');

      $pleaseWait.hide();
      if (params.warningExists === 'false') {
        $upgradeResultCheck
          .addClass('conf')
          .removeClass('fail')
          .html('<p>Upgrade complete</p>')
          .show();
        $infoStep.html('<h3>Upgrade Complete!</h3>');
      } else {
        $pleaseWait.hide();
        $upgradeResultCheck
          .addClass('fail')
          .removeClass('ok')
          .html('<p>Upgrade complete, but warning notifications has been found.</p>')
          .show('slow');
        $infoStep.html('<h3>Upgrade complete, but warning notifications has been found.</h3>');
      }


      for (i = 0; i < todoList.length; i += 1) {
        todoUl += '<li>' + todoList[i] + '</li>';
      }
      todoUl += '</ul>';
      $upgradeResultToDoList.append(todoUl);
      $upgradeResultToDoList.show();

      $(window).unbind('beforeunload');
    }

    function afterError(res) {
      var params = res.nextParams;
      if (params.next === '') {
        $(window).unbind('beforeunload');
      }
      $('#pleaseWait').hide();

      addQuickInfo(['unbind :) ']);
    }

    function afterRollback() {
      startProcess('rollback');
    }

    function afterRollbackComplete(res) {
      $('#pleaseWait').hide();
      $('#upgradeResultCheck')
        .addClass('ok')
        .removeClass('fail')
        .html('<p>Restoration complete.</p>')
        .show('slow');
      updateInfoStep('<h3>Restoration complete.</h3>');
      $(window).unbind();
    }


    function afterRestoreDb() {
      // $('#restoreBackupContainer').hide();
    }

    function afterRestoreFiles() {
      // $('#restoreFilesContainer').hide();
    }

    function afterBackupFiles() {
      // params = res.nextParams;
      // if (params.stepDone)
    }

    /**
     * afterBackupDb display the button
     *
     */
    function afterBackupDb(res) {
      var params = res.nextParams;
      var $selectRestoreName = $('select[name=restoreName]');

      if (res.stepDone && typeof window.upgrader.PS_AUTOUP_BACKUP !== 'undefined' && window.upgrader.PS_AUTOUP_BACKUP) {
        $('#restoreBackupContainer').show();
        $selectRestoreName.children('options').removeAttr('selected');
        $selectRestoreName.append('<option selected="selected" value="' + params.backupName + '">' + params.backupName + '</option>');
        $selectRestoreName.change();
      }
    }

    window.upgrader.availableFunctions = {
      startProcess: startProcess,
      afterUpdateConfig: afterUpdateConfig,
      isAllConditionOk: isAllConditionOk,
      afterUpgradeNow: afterUpgradeNow,
      afterUpgradeComplete: afterUpgradeComplete,
      afterError: afterError,
      afterRollback: afterRollback,
      afterRollbackComplete: afterRollbackComplete,
      afterRestoreDb: afterRestoreDb,
      afterRestoreFiles: afterRestoreFiles,
      afterBackupFiles: afterBackupFiles,
      afterBackupDb: afterBackupDb
    };


    function callFunction(func) {
      window.upgrader.availableFunctions[func].apply(this, Array.prototype.slice.call(arguments, 1));
    }

    function doAjaxRequest(action, nextParams) {
      var req;

      if (typeof window.upgrader._PS_MODE_DEV_ !== 'undefined' && window.upgrader._PS_MODE_DEV_) {
        addQuickInfo(['[DEV] ajax request : ' + action]);
      }
      $('#pleaseWait').show();
      req = $.ajax({
        type: 'POST',
        url: window.upgrader.ajaxLocation,
        async: true,
        data: {
          dir: window.upgrader.dir,
          ajax: '1',
          token: window.upgrader.token,
          ajaxToken: window.upgrader.ajaxToken,
          autoupgradeDir: window.token.autoupgradeDir,
          tab: 'AdminThirtyBeesMigrate',
          action: action,
          params: nextParams
        },
        beforeSend: function (jqXHR) {
          $.xhrPool.push(jqXHR);
        },
        complete: function () {
          // just remove the item to the 'abort list'
          $.xhrPool.pop();
          // $(window).unbind('beforeunload');
        },
        success: function (res, textStatus) {
          var $action = $('#' + action);
          var response;

          $('#pleaseWait').hide();
          try {
            response = JSON.parse(res);
          } catch (e) {
            response = {
              status: 'error',
              nextParams: nextParams,
            };
            window.swal('Javascript error (parseJSON) detected for action "' + action + '"Starting recovery process...');
          }
          addQuickInfo(response.nextQuickInfo);
          addError(response.nextErrors);
          updateInfoStep(response.nextDesc, response.step);
          window.upgrader.currentParams = response.nextParams;
          if (response.status === 'ok') {
            $action.addClass('done');
            if (response.stepDone) {
              $('#' + action).addClass('stepok');
            }
            // if a function 'after[action name]' exists, it should be called now.
            // This is used for enabling restore buttons for example
            var funcName = 'after' + ucFirst(action);
            if (typeof funcName === 'string' && eval('typeof ' + funcName) === 'function') {
              callFunction(funcName, response);
            }

            handleSuccess(response, action);
          } else {
            // display progression
            $action.addClass('done');
            $action.addClass('steperror');
            if (action !== 'rollback'
              && action !== 'rollbackComplete'
              && action !== 'restoreFiles'
              && action !== 'restoreDb'
              && action !== 'rollback'
              && action !== 'noRollbackFound'
            ) {
              handleError(response, action);
            } else {
              window.swal('Error detected during [' + action + ']');
            }
          }
        },
        error: function (jqXHR, textStatus, errorThrown) {
          $('#pleaseWait').hide();
          if (textStatus === 'timeout') {
            if (action === 'download') {
              updateInfoStep('Your server cannot download the file. Please upload it first by ftp in your admin/autoupgrade directory');
            } else {
              updateInfoStep('[Server Error] Timeout:The request exceeded the max_time_limit. Please change your server configuration.');
            }
          }
          else {
            updateInfoStep('[Ajax / Server Error for action ' + action + '] textStatus: "' + textStatus + ' " errorThrown:"' + errorThrown + ' " jqXHR: " ' + jqXHR.responseText + '"');
          }
        }
      });
      return req;
    }

    /**
     * prepareNextButton make the button button_selector available, and update the nextParams values
     *
     * @param {string} buttonSelector $button_selector
     * @param {object} nextParams $nextParams
     *
     * @return {void}
     */
    function prepareNextButton(buttonSelector, nextParams) {
      $(buttonSelector).unbind();
      $(buttonSelector).click(function (e) {
        e.preventDefault();
        $('#currentlyProcessing').show();

        window.upgrader.action = buttonSelector.substr(1);
        window.upgrader.res = doAjaxRequest(window.upgrader.action, nextParams);
      });
    }

    /**
     * handleSuccess
     * res = {error:, next:, nextDesc:, nextParams:, nextQuickInfo:,status:'ok'}
     *
     * @param {object} res
     * @param {string} action
     *
     * @return {void}
     */
    function handleSuccess(res, action) {
      if (res.next !== '') {

        $('#' + res.next).addClass('nextStep');
        if (window.upgrader.manualMode && (action !== 'rollback'
          && action !== 'rollbackComplete'
          && action !== 'restoreFiles'
          && action !== 'restoreDb'
          && action !== 'rollback'
          && action !== 'noRollbackFound')) {
          prepareNextButton('#' + res.next, res.nextParams);
          window.swal('Manually go to button ' + res.next);
        }
        else {
          // if next is rollback, prepare nextParams with rollbackDbFilename and rollbackFilesFilename
          if (res.next === 'rollback') {
            res.nextParams.restoreName = '';
          }
          doAjaxRequest(res.next, res.nextParams);
          // 2) remove all step link (or show them only in dev mode)
          // 3) when steps link displayed, they should change color when passed if they are visible
        }
      }
      else {
        // Way To Go, end of upgrade process
        addQuickInfo(['End of process']);
      }
    }

// res = {nextParams, nextDesc}
    function handleError(res, action) {
      var response = res;
      // display error message in the main process thing
      // In case the rollback button has been deactivated, just re-enable it
      $('#rollback').removeAttr('disabled');
      // auto rollback only if current action is upgradeFiles or upgradeDb
      if (action === 'upgradeFiles' || action === 'upgradeDb' || action === 'upgradeModules') {
        $('.button-autoupgrade').html('Operation canceled. Checking for restoration...');
        response.nextParams.restoreName = response.nextParams.backupName;
        // FIXME: show backup name
        window.swal({
          title: 'Migration failed :(',
          text: 'Do you want to restore from backup `' + response.nextParams.backupName + '`?',
          type: 'error',
          showCancelButton: true,
        })
          .then(
            function () {
              doAjaxRequest('rollback', response.nextParams);
            }
          );
      } else {
        $('.button-autoupgrade').html('Operation canceled. An error happened.');
        $(window).unbind();
      }
    }

    $(document).ready(function () {
      $('input[name|=submitConf]').bind('click', function (e) {
        var params = {};
        var newChannel = $('select[name=channel] option:selected').val();
        var oldChannel = $('select[name=channel] option.current').val();
        if (oldChannel !== newChannel) {
          if (newChannel === 'stable'
            || newChannel === 'rc'
            || newChannel === 'beta'
            || newChannel === 'alpha') {
            params.channel = newChannel;
          }
        }

        window.upgrader.res = doAjaxRequest('updateConfig', params);
      });
    });
  }

  initializeUpgrader();
}());
