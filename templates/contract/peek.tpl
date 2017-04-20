
<form action="{devblocks_url}{/devblocks_url}" method="post" id="frmContractPeek">
<input type="hidden" name="c" value="profiles">
<input type="hidden" name="a" value="handleSectionAction">
<input type="hidden" name="section" value="contract">
<input type="hidden" name="action" value="savePeek">
<input type="hidden" name="view_id" value="{$view_id}">
{if !empty($model) && !empty($model->id)}<input type="hidden" name="id" value="{$model->id}">{/if}
<input type="hidden" name="do_delete" value="0">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">
	{if $model&& !empty($model->org)}
		{$org=DAO_ContactOrg::get($model->org)}
	{/if}

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

{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/peek_custom_fieldsets.tpl" context='cerberusweb.contexts.contract' context_id=$model->id}

{* Comments *}
{include file="devblocks:cerberusweb.core::internal/peek/peek_comments_pager.tpl" comments=$comments}

<fieldset class="peek">
	<legend>{'common.comment'|devblocks_translate|capitalize}</legend>
	<textarea name="comment" rows="2" cols="45" style="width:98%;" placeholder="{'comment.notify.at_mention'|devblocks_translate}"></textarea>
</fieldset>

{if !empty($model->id)}
<fieldset style="display:none;" class="delete">
	<legend>{'common.delete'|devblocks_translate|capitalize}</legend>
	
	<div>
		Are you sure you want to delete this contract?
	</div>
	
	<button type="button" class="delete" onclick="var $frm=$(this).closest('form');$frm.find('input:hidden[name=do_delete]').val('1');$frm.find('button.submit').click();"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> Confirm</button>
	<button type="button" onclick="$(this).closest('form').find('div.buttons').fadeIn();$(this).closest('fieldset.delete').fadeOut();"><span class="glyphicons glyphicons-circle-minus" style="color:rgb(200,0,0);"></span> {'common.cancel'|devblocks_translate|capitalize}</button>
</fieldset>
{/if}

<div class="buttons">
	<button type="button" class="submit" onclick="genericAjaxPopupPostCloseReloadView(null,'frmContractPeek','{$view_id}', false, 'contract_save');"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
	{if !empty($model->id)}<button type="button" onclick="$(this).parent().siblings('fieldset.delete').fadeIn();$(this).closest('div').fadeOut();"><span class="glyphicons glyphicons-circle-remove" style="color:rgb(200,0,0);"></span> {'common.delete'|devblocks_translate|capitalize}</button>{/if}
</div>

{if !empty($model->id)}
<div style="float:right;">
	<a href="{devblocks_url}c=profiles&type=contract&id={$model->id}-{$model->name|devblocks_permalink}{/devblocks_url}">view full record</a>
</div>
<br clear="all">
{/if}
</form>

<script type="text/javascript">
	var $popup = genericAjaxPopupFetch('peek');
	var $chooser_org = $popup.find('button.chooser-abstract[data-field-name="org"]');
	$popup.find('button.chooser-abstract')
			.cerbChooserTrigger()
			.on('cerb-chooser-saved', function(e) {
				// When the org changes, default the contact chooser filter
				if($(e.target).attr('data-field-name') == 'org') {
					var $bubble = $chooser_org.siblings('ul.chooser-container').find('> li:first input:hidden');
					var $button_create_contact = $chooser_contact.siblings('button.chooser-create');

					if($bubble.length > 0) {
						var org_id = $bubble.val();
						$chooser_contact.attr('data-query', 'org.id:' + org_id);

						// If there's a contact create button, change its defaults to the form contents
						$button_create_contact.attr('data-edit', '{if $address}email:{$address->id}{/if} org:' + org_id);
					}

				}
			})
	;

	$popup.one('popup_open', function(event,ui) {
		var $textarea = $(this).find('textarea[name=comment]');
		
		$(this).dialog('option','title',"{'Contract'|escape:'javascript' nofilter}");
		
		$(this).find('button.chooser_watcher').each(function() {
			ajax.chooser(this,'cerberusweb.contexts.worker','add_watcher_ids', { autocomplete:true });
		});
        /*$(this).find('button.chooser_organization').each(function() {
            ajax.chooser(this,'cerberusweb.contexts.org','org', { autocomplete:true });
        });*/
		$(this).find('input:text:first').focus();
		
		// @mentions
		
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
</script>
