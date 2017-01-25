<?php defined('C5_EXECUTE') or die("Access Denied."); ?>

<?php echo Loader::helper('concrete/ui')->tabs(array(
    array('form-add', t('Add'), true),
    array('form-edit', t('Edit')),
    array('form-results', t('Results')),
    array('form-options', t('Options')),
));?>

<div id="ccm-tab-content-form-add" class="ccm-tab-content" data-action="<?php echo $view->action('add_control')?>">
    <div class="alert alert-success" style="display: none"><?php echo t('Field added successfully.')?></div>
    <fieldset>
        <legend><?php echo t('New Question')?></legend>

        <div data-view="add-question-inner">

        </div>

        <div class="form-group" data-group="add-question" style="display: none">

            <button type="button" class="btn btn-primary" data-action="add-question"><?php echo t('Add Question')?></button>
        </div>

    </fieldset>

</div>

<div id="ccm-tab-content-form-edit" class="ccm-tab-content" data-action="<?php echo $view->action('update_control')?>">

    <div class="alert alert-success" style="display: none"><?php echo t('Field updated successfully.')?></div>

    <div data-view="form-fields">

    <fieldset>
        <legend><?php echo t('Fields')?></legend>

        <ul class="list-group">
        </ul>
    </fieldset>

    </div>

    <div data-view="edit-question" style="display: none">

        <fieldset>
            <legend><?php echo t('Edit Question')?></legend>

            <div data-view="edit-question-inner">

            </div>

            <div class="form-group">
                <hr/>
                <button type="button" class="btn btn-default" data-action="cancel-edit"><?php echo t('Cancel')?></button>
                <button type="button" class="btn btn-primary pull-right" data-action="update-question"><?php echo t('Save Question')?></button>
            </div>

        </fieldset>

    </div>

</div>


<div id="ccm-tab-content-form-results" class="ccm-tab-content" >
    <fieldset>
        <input type="hidden" name="resultsFolder" value="<?php echo $resultsFolder?>">

        <legend><?php echo t('Results')?></legend>
        <p><?php echo t('Store results in a folder:')?></p>

        <?php if (is_object($tree)) {
        ?>
        <div data-root-tree-node-id="<?php echo $formResultsRootFolderNodeID?>" data-tree="<?php echo $tree->getTreeID()?>">
        </div>
        <?php } ?>


    </fieldset>

</div>

<div id="ccm-tab-content-form-options" class="ccm-tab-content">
    <fieldset>
        <legend><?php echo t('Options')?></legend>
        <div class="form-group">
            <?php echo $form->label('formName', t('Form Name'))?>
            <?php echo $form->text('formName', $formName)?>
        </div>
        <div class="form-group">
            <?php echo $form->label('subjectAdmin', t('Subject to Admin'))?>
            <?php echo $form->text('subjectAdmin', $subjectAdmin)?>
        </div>
        <div class="form-group">
            <?php echo $form->label('subjectUser', t('Subject to User'))?>
            <?php echo $form->text('subjectUser', $subjectUser)?>
        </div>
        <div class="form-group">
            <?php echo $form->label('fromNameToUser', t('Form Name to User'))?>
            <?php echo $form->text('fromNameToUser', $fromNameToUser)?>
        </div>

        <div class="form-group">
            <?php echo $form->label('fromAddressToUser', t('Form Address to User'))?>
            <?php echo $form->text('fromAddressToUser', $fromAddressToUser)?>
        </div>

        <div class="form-group">
            <?php echo $form->label('submitLabel', t('Comfirm Button Label'))?>
            <?php echo $form->text('submitLabel', $submitLabel)?>
        </div>
        <div class="form-group">
            <?php echo $form->label('sendLabel', t('Send Button Label'))?>
            <?php echo $form->text('sendLabel', $sendLabel)?>
        </div>
        <div class="form-group">
            <?php echo $form->label('preambleAdmin', t('Preamble for admin'))?>
            <?php echo $form->textarea('preambleAdmin', $preambleAdmin, array('rows' => 3))?>
        </div>
        <div class="form-group">
            <?php echo $form->label('signatureAdmin', t('Signature for admin'))?>
            <?php echo $form->textarea('signatureAdmin', $signatureAdmin, array('rows' => 3))?>
        </div>

        <div class="form-group">
            <?php echo $form->label('preambleUser', t('Preamble for user'))?>
            <?php echo $form->textarea('preambleUser', $preambleUser, array('rows' => 3))?>
        </div>
        <div class="form-group">
            <?php echo $form->label('signatureUser', t('Signature for user'))?>
            <?php echo $form->textarea('signatureUser', $signatureUser, array('rows' => 3))?>
        </div>
        <div class="form-group">
            <?php echo $form->label('thankyouMsg', t('Message to display when completed'))?>
            <?php echo $form->textarea('thankyouMsg', $thankyouMsg, array('rows' => 3))?>
        </div>
        <div class="form-group">
            <?php echo $form->label('recipientEmail', t('Send form submissions to email addresses'))?>
            <div class="input-group">
                <span class="input-group-addon" style="z-index: 2000">
                <?php echo $form->checkbox('notifyMeOnSubmission', 1, $notifyMeOnSubmission == 1)?>
                </span><?php echo $form->text('recipientEmail', $recipientEmail, array('style' => 'z-index:2000;'))?>
            </div>
            <span class="help-block"><?php echo t('(Seperate multiple emails with a comma)')?></span>
        </div>
        <div data-view="form-options-email-reply-to"></div>
        <div class="form-group">
            <label class="control-label"><?php echo t('Solving a <a href="%s" target="_blank">CAPTCHA</a> Required to Post?', t('http://en.wikipedia.org/wiki/Captcha'))?></label>
            <div class="radio">
                <label>
                    <?php echo $form->radio('displayCaptcha', 1, (int) $displayCaptcha)?>
                    <span><?php echo t('Yes')?></span>
                </label>
            </div>
            <div class="radio">
                <label>
                    <?php echo $form->radio('displayCaptcha', 0, (int) $displayCaptcha)?>
                    <span><?php echo t('No')?></span>
                </label>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label" for="ccm-form-redirect"><?php echo t('Redirect to another page after form submission?')?></label>
            <div id="ccm-form-redirect-page">
                <?php
                $page_selector = Loader::helper('form/page_selector');
                if ($redirectCID) {
                    echo $page_selector->selectPage('redirectCID', $redirectCID);
                } else {
                    echo $page_selector->selectPage('redirectCID');
                }
                ?>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label" for="ccm-form-fileset"><?php echo t('Add uploaded files to a set?')?></label>
                <?php

                $fileSets = Concrete\Core\File\Set\Set::getMySets();
                $sets = array(0 => t('None'));
                foreach ($fileSets as $fileSet) {
                    $sets[$fileSet->getFileSetID()] = $fileSet->getFileSetDisplayName();
                }
                echo $form->select('addFilesToSet', $sets, $addFilesToSet);
                ?>
        </div>

    </fieldset>
</div>

<script type="text/template" data-template="express-form-form-control">
<li class="list-group-item"
    data-action="<?php echo $view->action('get_control')?>"
    data-form-control-field-type="<%=control.attributeType%>"
    data-form-control-label="<%=control.displayLabel%>"
    data-form-control-id="<%=control.id%>">
    <input type="hidden" name="controlID[]" value="<%=control.id%>">
    <%=control.displayLabel%>
    <span class="pull-right">
        <i style="cursor: move" class="fa fa-arrows"></i>
        <a href="javascript:void(0)" class="icon-link" data-action="edit-control"><i class="fa fa-pencil"></i></a>
        <a href="javascript:void(0)" class="icon-link" data-action="delete-control"><i class="fa fa-trash"></i></a>
        </span>
    <% if (control.isRequired) { %>
    <span style="margin-right: 20px" class="badge badge-info"><?php echo t('Required')?></span>
    <% } %>
</li>
</script>

<script type="text/template" data-template="express-form-reply-to-email">
    <div class="form-group">
        <?php echo $form->label('replyToEmailControlID', t('Set value of Reply-To to Email Field'))?>
        <select name="replyToEmailControlID" class="form-control">
            <option value=""><?php echo t('** None')?></option>
            <% _.each(controls, function(control){ %>
            <option value="<%=control.key%>" <% if (selected == control.key) { %>selected<% } %>><%=_.escape(control.value)%></option>
            <% }); %>
        </select>
    </div>
</script>

<script type="text/template" data-template="express-form-form-question">

    <% if (id) { %>
        <input type="hidden" name="id" value="<%=id%>">
    <% } %>

    <div class="form-group" data-action="<?php echo $view->action('get_type_form')?>" data-group="field-types">
        <?php echo $form->label('type', t('Answer Type'))?>

        <% if (!id) { %>
            &nbsp; <i class="fa fa-refresh fa-spin" style="display: none"></i>
            <select name="type" class="form-control">
                <option value=""><?php echo t('** Choose Field')?></option>
            <% _.each(types, function(group) { %>
                <optgroup label="<%=group.label%>">
                    <% _.each(group.fields, function(type) { %>
                        <option value="<%=type.id%>" <% if (selectedType == type.id) { %>selected<% } %>><%=_.escape(type.displayName)%></option>
                    <% }); %>
                </optgroup>
            <% }); %>
            </select>
        <% } else { %>
            <input type="hidden" name="type" value="<%=selectedType%>">
            <div><strong><%=selectedTypeDisplayName%></strong></div>
        <% } %>
    </div>

    <div class="form-group" data-group="control-name" style="display: none">
        <?php echo $form->label('question', t('Question'))?>
        <input type="text" name="question" class="form-control" maxlength="255" value="<%=question%>">
    </div>

    <% if (typeContent) { %>
        <div data-group="field-type-data"><%=typeContent%></div>
    <% } else { %>
        <div style="display: none" data-group="field-type-data"></div>
    <% } %>

    <div class="form-group" data-group="control-required" style="display: none">
        <label class="control-label"><?php echo t('Required')?></label>
        <div class="radio"><label>
            <input type="radio" name="required<% if (id) { %>Edit<% } %>" value="1" <% if (isRequired) { %>checked<% } %>>
            <?php echo t('Yes')?>
        </label></div>
        <div class="radio"><label>
            <input type="radio" name="required<% if (id) { %>Edit<% } %>" value="0" <% if (!isRequired) { %>checked<% } %>>
            <?php echo t('No')?>
        </label></div>
    </div>
</script>


<script type="application/javascript">
    Concrete.event.publish('open.block_express_form', {
        controls: <?php echo json_encode($controls)?>,
        types: <?php echo json_encode($types_select)?>,
        settings: {
            'replyToEmailControlID': <?php echo json_encode($replyToEmailControlID)?>
        }
    });
</script>