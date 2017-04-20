{$form_id = uniqid()}
<form action="{devblocks_url}{/devblocks_url}" method="post" id="{$form_id}" onsubmit="return false;">
    <input type="hidden" name="c" value="profiles">
    <input type="hidden" name="a" value="handleSectionAction">
    <input type="hidden" name="section" value="contract">
    <input type="hidden" name="action" value="savePeekJson">
    <input type="hidden" name="id" value="{$model->id}">
    <input type="hidden" name="view_id" value="{$view_id}">
    <input type="hidden" name="do_delete" value="0">
    <input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

    <fieldset class="peek">
        <legend>{'common.properties'|devblocks_translate}</legend>

        <table cellspacing="0" cellpadding="2" border="0" width="98%">
            <tr>
                <td width="1%" nowrap="nowrap"><b>{'common.name'|devblocks_translate}:</b></td>
                <td width="99%">
                    <input type="text" name="name" value="{$model->name}" style="width:98%;">
                </td>
            </tr>
            {*Organization*}
            <tr>
                <td width="0%" nowrap="nowrap" valign="top" align="right">{'common.organization'|devblocks_translate|capitalize}: </td>
                <td width="100%">
                    <button type="button" class="chooser-abstract" data-field-name="org" data-context="{CerberusContexts::CONTEXT_ORG}" data-single="true" data-autocomplete="if-null" data-create="if-null"><span class="glyphicons glyphicons-search"></span></button>

                    <ul class="bubbles chooser-container">
                        {if $org}
                            <li><img class="cerb-avatar" src="{devblocks_url}c=avatars&context=org&context_id={$org->id}{/devblocks_url}?v={$org->updated}"><input type="hidden" name="org" value="{$org->id}"><a href="javascript:;" class="cerb-peek-trigger no-underline" data-context="{CerberusContexts::CONTEXT_ORG}" data-context-id="{$org->id}">{$org->name}</a></li>
                        {/if}
                    </ul>
                </td>
            </tr>
            {* Watchers *}
            <tr>
                <td width="0%" nowrap="nowrap" valign="top" align="right">{'common.watchers'|devblocks_translate|capitalize}: </td>
                <td width="100%">
                    {if empty($model->id)}
                        <button type="button" class="chooser_watcher"><span class="glyphicons glyphicons-search"></span></button>
                        <ul class="chooser-container bubbles" style="display:block;"></ul>
                    {else}
                        {$object_watchers = DAO_ContextLink::getContextLinks('cerberusweb.contexts.contract', array($model->id), CerberusContexts::CONTEXT_WORKER)}
                        {include file="devblocks:cerberusweb.core::internal/watchers/context_follow_button.tpl" context='cerberusweb.contexts.contract' context_id=$model->id full=true}
                    {/if}
                </td>
            </tr>
        </table>

    </fieldset>

    {if !empty($custom_fields)}
        <fieldset class="peek">
            <legend>{'common.custom_fields'|devblocks_translate}</legend>
            {include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=false}
        </fieldset>
    {/if}

    {include file="devblocks:cerberusweb.core::internal/custom_fieldsets/peek_custom_fieldsets.tpl" context=CerberusContexts::CONTEXT_CONTRACT context_id=$model->id}

    <fieldset class="peek">
        <legend>{'common.comment'|devblocks_translate|capitalize}</legend>
        <textarea name="comment" rows="2" cols="45" style="width:98%;" placeholder="{'comment.notify.at_mention'|devblocks_translate}"></textarea>
    </fieldset>

    {if !empty($model->id)}
        <fieldset style="display:none;" class="delete">
            <legend>{'common.delete'|devblocks_translate|capitalize}</legend>

            <div>
                Are you sure you want to permanently delete this contract?
            </div>

            <button type="button" class="delete red"></span> {'common.yes'|devblocks_translate|capitalize}</button>
            <button type="button" onclick="$(this).closest('form').find('div.buttons').fadeIn();$(this).closest('fieldset.delete').fadeOut();"></span> {'common.no'|devblocks_translate|capitalize}</button>
        </fieldset>
    {/if}

    <div class="status"></div>

    <div class="buttons">
        <button type="button" class="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
        {if !empty($model->id)}<button type="button" onclick="$(this).parent().siblings('fieldset.delete').fadeIn();$(this).closest('div').fadeOut();"><span class="glyphicons glyphicons-circle-remove" style="color:rgb(200,0,0);"></span> {'common.delete'|devblocks_translate|capitalize}</button>{/if}
    </div>

</form>

<script type="text/javascript">
    $(function() {
        var $frm = $('#{$form_id}');
        var $popup = genericAjaxPopupFind($frm);

        $popup.one('popup_open', function(event,ui) {
            $popup.dialog('option','title',"{'contracts.common.contract'|devblocks_translate|escape:'javascript' nofilter}");

            // Buttons
            $popup.find('button.submit').click(Devblocks.callbackPeekEditSave);
            $popup.find('button.delete').click({ mode: 'delete' }, Devblocks.callbackPeekEditSave);
            // Abstract choosers
            $popup.find('button.chooser-abstract').cerbChooserTrigger();

            // @mentions

            var $textarea = $(this).find('textarea[name=comment]');

            var atwho_workers = {CerberusApplication::getAtMentionsWorkerDictionaryJson() nofilter};

            $textarea.atwho({
                at: '@',
                {literal}displayTpl: '<li>${name} <small style="margin-left:10px;">${title}</small> <small style="margin-left:10px;">@${at_mention}</small></li>',{/literal}
                {literal}insertTpl: '@${at_mention}',{/literal}
                data: atwho_workers,
                searchKey: '_index',
                limit: 10
            });
        });
    });
</script>