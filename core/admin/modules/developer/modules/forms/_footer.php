<?php
	namespace BigTree;
?>
<script>
	BigTree.localCurrentFieldKey = false;
	BigTree.localHooks = function() {
		$("#resource_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
		BigTreeCustomControls();
	};

	$("#form_table").change(function(event,data) {
		$("#field_area").load("<?=ADMIN_ROOT?>ajax/developer/load-form/", { table: data.value }, BigTree.localHooks);
		$("#create").show();
	});

	$("#manage_hooks").click(function() {
		var data = $.parseJSON($("#form_hooks").val());
		var html = '<fieldset><label><?=Text::translate("Editing Hook")?></label><input type="text" name="edit" value="' + htmlspecialchars(data.edit ? data.edit : "") + '" /></fieldset>';
		html += '<fieldset><label><?=Text::translate("Pre-processing Hook")?></label><input type="text" name="pre" value="' + htmlspecialchars(data.pre ? data.pre : "") + '" /></fieldset>';
		html += '<fieldset><label><?=Text::translate("Post-processing Hook")?></label><input type="text" name="post" value="' + htmlspecialchars(data.post ? data.post : "") + '" /></fieldset>';
		html += '<fieldset><label><?=Text::translate("Publishing Hook")?></label><input type="text" name="publish" value="' + htmlspecialchars(data.publish ? data.publish : "") + '" /></fieldset>';
		
		BigTreeDialog({
			title: "<?=Text::translate("Manage Hooks", true)?>",
			content: html,
			helpLink: "http://www.bigtreecms.org/docs/dev-guide/modules/advanced-techniques/form-hooks/",
			icon: "edit",
			callback: function(data) {
				$("#form_hooks").val(JSON.stringify(data));
			}
		});
		
		return false;
	});
	
	$("#field_area").on("click",".icon_settings",function(ev) {
		ev.preventDefault();

		// Prevent double clicks
		if (BigTree.Busy) {
			return;
		}

		var key = $(this).attr("name");
		BigTree.localCurrentFieldKey = key;
		
		BigTreeDialog({
			title: "<?=Text::translate("Field Options", true)?>",
			url: "<?=ADMIN_ROOT?>ajax/developer/load-field-options/",
			post: { table: $("#form_table").val(), type: $("#type_" + key).val(), data: $("#options_" + key).val() },
			icon: "edit",
			callback: function(data) {
				$("#options_" + BigTree.localCurrentFieldKey).val(JSON.stringify(data));
			}
		});
		
	}).on("click",".icon_delete",function() {
		var li = $(this).parents("li");
		var title = li.find("input").val();
		if (title) {
			var key = $(this).attr("name");
			if (key != "geocoding" && key.indexOf("__mtm-") != 0) {
				BigTree.localFieldSelect.addField(key,title);
			}
		}
		li.remove();
		return false;
	});
	
	$("#field_area").on("click",".add_geocoding",function() {
		var li = $('<li id="row_geocoding">');
		li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="fields[__geocoding__][title]" value="<?=Text::translate("Geocoding", true)?>" disabled="disabled" /></section>' + 
				'<section class="developer_resource_form_subtitle"><input type="text" name="fields[__geocoding__][subtitle]" value="" disabled="disabled" /></section>' + 
				'<section class="developer_resource_type"><input name="fields[__geocoding__][type]" id="type_geocoding" type="hidden" value="geocoding" /><span class="resource_name"><?=Text::translate("Geocoding", true)?></span><a href="#" class="options icon_settings" name="geocoding"></a><input type="hidden" name="fields[__geocoding__][options]" value="" id="options_geocoding" /></section>' +
				'<section class="developer_resource_action"><a href="#" class="icon_delete" name="geocoding"></a></section>');
		
		$("#resource_table").append(li);
		BigTree.localHooks();
		li.find(".icon_settings").trigger("click");
		
		return false;
	});
	
	$("#field_area").on("click",".add_many_to_many",function() {
		BigTree.localMTMCount++;
			
		var li = $('<li id="mtm_row_' + BigTree.localMTMCount + '">');
		li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="fields[__mtm-' + BigTree.localMTMCount + '__][title]" value="" /></section>' +
				'<section class="developer_resource_form_subtitle"><input type="text" name="fields[__mtm-' + BigTree.localMTMCount + '__][subtitle]" value="" /></section>' +
				'<section class="developer_resource_type"><input name="fields[__mtm-' + BigTree.localMTMCount + '__][type]" id="type___mtm-' + BigTree.localMTMCount + '__" type="hidden" value="many-to-many" /><span class="resource_name"><?=Text::translate("Many To Many", true)?></span><a href="#" class="options icon_settings" name="__mtm-' + BigTree.localMTMCount + '__"></a><input type="hidden" name="fields[__mtm-' + BigTree.localMTMCount + '__][options]" value="" id="options___mtm-' + BigTree.localMTMCount + '__" /></section>' +
				'<section class="developer_resource_action"><a href="#" class="icon_delete" name="__mtm-' + BigTree.localMTMCount + '__"></a></section>');
		
		$("#resource_table").append(li);
		BigTree.localHooks();
		li.find(".icon_settings").trigger("click");
		
		return false;
	});
	
	BigTree.localHooks();
	BigTreeFormValidator("form.module");
</script>	