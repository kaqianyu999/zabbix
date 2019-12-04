<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


ob_start(); ?>

/**
 * Make step result UI element.
 *
 * @param array step  Step object returned from server.
 *
 * @return jQuery
 */
function makeStepResult(step) {
	if (typeof step.error !== 'undefined') {
		return jQuery(new Template(jQuery('#preprocessing-step-error-icon').html()).evaluate(
			{error: step.error || <?= CJs::encodeJson(_('<empty string>')) ?>}
		));
	}
	else if (typeof step.result === 'undefined' || step.result === null) {
		return jQuery('<span>', {'class': '<?= ZBX_STYLE_GREY ?>'}).text(<?= CJs::encodeJson(_('No value')) ?>);
	}
	else if (step.result === '') {
		return jQuery('<span>', {'class': '<?= ZBX_STYLE_GREY ?>'}).text(<?= CJs::encodeJson(_('<empty string>')) ?>);
	}
	else if (step.result.indexOf("\n") != -1 || step.result.length > 25) {
		return jQuery(new Template(jQuery('#preprocessing-step-result').html()).evaluate(
			jQuery.extend({result: step.result})
		));
	}
	else {
		return jQuery('<span>').text(step.result);
	}
}

function disableItemTestForm() {
	jQuery('#value, #time, [name^=macros]').prop('disabled', true);

	<?php if ($data['is_item_testable'] && $data['interface_enabled']) { ?>
	jQuery('#get_value, #interface_address, #interface_port, #get_value_btn').prop('disabled', true);
	<?php } else { ?>
	jQuery('#get_value, #get_value_btn').prop('disabled', true);
	<?php } ?>

	<?php if ($data['proxies_enabled']) { ?>
	jQuery('#host_proxy').prop('disabled', true);
	<?php } ?>

	<?php if ($data['show_prev']) { ?>
	jQuery('#prev_value, #prev_time').prop('disabled', true);
	<?php } ?>

	jQuery('#eol input').prop('disabled', true);

	<?php if (count($data['steps']) > 0) { ?>
	jQuery('<span>')
		.addClass('preloader')
		.insertAfter(jQuery('.submit-test-btn'))
		.css({
			'display': 'inline-block',
			'margin': '0 10px -8px'
		});

	jQuery('.submit-test-btn')
		.prop('disabled', true)
		.hide();
	<?php } ?>
}

function enableItemTestForm() {
	jQuery('#value, #time, [name^=macros]').prop('disabled', false);

	<?php if ($data['is_item_testable'] && $data['interface_enabled']) { ?>
	jQuery('#get_value, #interface_address, #interface_port, #get_value_btn').prop('disabled', false);
	<?php } else { ?>
	jQuery('#get_value, #get_value_btn').prop('disabled', false);
	<?php } ?>

	<?php if ($data['proxies_enabled']) { ?>
	jQuery('#host_proxy').prop('disabled', false);
	<?php } ?>

	<?php if ($data['show_prev']) { ?>
	jQuery('#prev_value, #prev_time').prop('disabled', false);
	<?php } ?>

	jQuery('#eol input').prop('disabled', false);

	<?php if (count($data['steps']) > 0) { ?>
	jQuery('.preloader').remove();
	jQuery('.submit-test-btn')
		.prop('disabled', false)
		.show();
	<?php } ?>
}

function cleanPreviousTestResults() {
	var $form = jQuery('#preprocessing-test-form');

	jQuery('[id^="preproc-test-step-"][id$="-result"]', $form).empty();
	jQuery('[id^="preproc-test-step-"][id$="-name"] > div', $form).remove();
	jQuery('#final-result', $form)
		.hide()
		.find('.table-forms-td-right')
		.empty();

	jQuery('#value-mapped-result', $form)
		.hide()
		.find('.table-forms-td-right > *')
		.not('.grey')
		.remove();
}

/**
 * Send item get value request and display retrieved results.
 */
function itemGetValueTest() {
	var $form = jQuery('#preprocessing-test-form'),
		post_data = getItemTestProperties('#preprocessing-test-form'),
		url = new Curl('zabbix.php');

	url.setArgument('action', 'popup.itemtest.getvalue');

	post_data = jQuery.extend(post_data, {
		interface: {
			address: jQuery('#interface_address', $form).val(),
			port: jQuery('#interface_port', $form).val()
		},
		host_proxy: jQuery('#host_proxy', $form).val(),
		test_type: <?= $data['test_type'] ?>,
		hostid: <?= $data['hostid'] ?>,
		value: jQuery('#value', $form).multilineInput('value')
	});

	<?php if ($data['show_prev']) { ?>
	post_data['time_change'] = (jQuery('#upd_prev').val() !== '')
		? parseInt(jQuery('#upd_last').val()) - parseInt(jQuery('#upd_prev').val())
		: Math.ceil(+new Date()/1000) - parseInt(jQuery('#upd_last').val());
	<?php } ?>

	delete post_data.interfaceid;
	delete post_data.delay;

	jQuery.ajax({
		url: url.getUrl(),
		data: post_data,
		beforeSend: function() {
			disableItemTestForm();
			cleanPreviousTestResults();
		},
		success: function(ret) {
			$form.parent().find('.msg-bad, .msg-good').remove();

			if (typeof ret.messages !== 'undefined') {
				jQuery(ret.messages).insertBefore($form);
			}
			else {
				<?php if ($data['show_prev']) { ?>
				if (typeof ret.prev_value !== 'undefined') {
					jQuery('#prev_value', $form).multilineInput('value', ret.prev_value);
					jQuery('#prev_time', $form).val(ret.prev_time);

					jQuery('#upd_prev', $form).val(jQuery('#upd_last', $form).val());
					jQuery('#upd_last', $form).val(Math.ceil(+new Date()/1000));
				}
				<?php } ?>

				jQuery('#value', $form).multilineInput('value', ret.value);

				if (typeof ret.eol !== 'undefined') {
					jQuery("input[value="+ret.eol+"]", jQuery("#eol")).prop("checked", "checked");
				}
			}

			enableItemTestForm();
		},
		dataType: 'json',
		type: 'post'
	});
}

/**
 * Send item preprocessing test details and display results in table.
 *
 * @param string formid  Selector for form to send.
 */
function itemCompleteTest() {
	var $form = jQuery('#preprocessing-test-form'),
		url = new Curl('zabbix.php'),
		is_prev_enabled = <?= $data['show_prev'] ? 'true' : 'false' ?>,
		post_data = getItemTestProperties('#preprocessing-test-form'),
		step_nums = [];

	url.setArgument('action', 'popup.itemtest.send');

	if (<?= $data['steps_num']; ?> > 0) {
		step_nums = [...Array(<?= $data['steps_num']; ?>).keys()];
	}
	else {
		step_nums = [0];
	}

	post_data = jQuery.extend(post_data, {
		get_value: jQuery('#get_value', $form).is(":checked") ? 1 : 0,
		steps: getPreprocessingSteps(step_nums),
		interface: {
			address: jQuery('#interface_address', $form).val(),
			port: jQuery('#interface_port', $form).val()
		},
		host_proxy: jQuery('#host_proxy', $form).val(),
		show_final_result: <?= $data['show_final_result'] ? 1 : 0 ?>,
		test_type: <?= $data['test_type'] ?>,
		hostid: <?= $data['hostid'] ?>,
		valuemapid: <?= $data['valuemapid'] ?>,
		value: jQuery('#value', $form).multilineInput('value')
	});

	<?php if ($data['show_prev']) { ?>
	if (post_data.get_value) {
		post_data['time_change'] = (jQuery('#upd_prev').val() !== '')
			? parseInt(jQuery('#upd_last').val()) - parseInt(jQuery('#upd_prev').val())
			: Math.ceil(+new Date()/1000) - parseInt(jQuery('#upd_last').val());
	}

	post_data = jQuery.extend(post_data, {
		prev_time: jQuery('#prev_time', $form).val(),
		prev_value: jQuery('#prev_value', $form).multilineInput('value')
	});
	<?php } ?>

	jQuery.ajax({
		url: url.getUrl(),
		data: post_data,
		beforeSend: function() {
			disableItemTestForm();
			cleanPreviousTestResults();
		},
		success: function(ret) {
			$form.parent().find('.msg-bad, .msg-good').remove();

			if (typeof ret.messages !== 'undefined') {
				jQuery(ret.messages).insertBefore($form);
			}

			processItemPreprocessingTestResults(ret.steps);

			<?php if ($data['show_prev']) { ?>
			if (typeof ret.prev_value !== 'undefined') {
				jQuery('#prev_value', $form).multilineInput('value', ret.prev_value);
				jQuery('#prev_time', $form).val(ret.prev_time);

				jQuery('#upd_prev', $form).val(jQuery('#upd_last', $form).val());
				jQuery('#upd_last', $form).val(Math.ceil(+new Date()/1000));
			}
			<?php } ?>

			jQuery('#value', $form).multilineInput('value', ret.value);

			if (typeof ret.eol !== 'undefined') {
				jQuery("input[value="+ret.eol+"]", jQuery("#eol")).prop("checked", "checked");
			}

			if (typeof ret.final !== 'undefined') {
				var result = makeStepResult(ret.final);
				if (result !== null) {
					$result = jQuery(result).css('float', 'right');
				}

				jQuery('#final-result')
					.show()
					.find('.table-forms-td-right')
						.append(ret.final.action)
						.append($result);

				if (typeof ret.mapped_value != 'undefined') {
					$mapped_value = makeStepResult({result: ret.mapped_value});
					$mapped_value.css('float', 'right');

					jQuery('#value-mapped-result')
						.show()
						.find('.table-forms-td-right')
						.append($mapped_value);
				}
				else {
					jQuery('#value-mapped-result').hide();
				}
			}

			enableItemTestForm();
		},
		dataType: 'json',
		type: 'post'
	});
}

/**
 * Process test results and make visual changes in test dialog results block.
 *
 * @param array steps  Array of objects containing details about each preprocessing step test results.
 */
function processItemPreprocessingTestResults(steps) {
	var tmpl_gray_label = new Template(jQuery('#preprocessing-gray-label').html()),
		tmpl_act_done = new Template(jQuery('#preprocessing-step-action-done').html());

	steps.each(function(step, i) {
		if (typeof step.action !== 'undefined') {
			switch (step.action) {
				case <?= ZBX_PREPROC_FAIL_DEFAULT ?>:
					step.action = null;
					break;

				case <?= ZBX_PREPROC_FAIL_DISCARD_VALUE ?>:
					step.action = jQuery(tmpl_gray_label.evaluate(<?= CJs::encodeJson([
						'label' => _('Discard value')
					]) ?>));
					break;

				case <?= ZBX_PREPROC_FAIL_SET_VALUE ?>:
					step.action = jQuery(tmpl_act_done.evaluate(jQuery.extend(<?= CJs::encodeJson([
						'action_name' => _('Set value to')
					]) ?>, {failed: step.result})));
					break;

				case <?= ZBX_PREPROC_FAIL_SET_ERROR ?>:
					step.action = jQuery(tmpl_act_done.evaluate(jQuery.extend(<?= CJs::encodeJson([
						'action_name' => _('Set error to')
					]) ?>, {failed: step.failed})));
					break;
			}
		}

		step.result = makeStepResult(step);

		if (typeof step.action !== 'undefined' && step.action !== null) {
			jQuery('#preproc-test-step-'+i+'-name').append(jQuery(tmpl_gray_label.evaluate(<?= CJs::encodeJson([
				'label' => _('Custom on fail')
			]) ?>)));
		}

		jQuery('#preproc-test-step-'+i+'-result').append(step.result, step.action);
	});
}

/**
 * Collect values from opened item test dialog and save input values for repeated use.
 */
function saveItemTestInputs() {
	var $form = jQuery('#preprocessing-test-form'),
		$test_obj,
		input_values = {
			value: jQuery('#value').multilineInput('value'),
			eol: jQuery('#eol').find(':checked').val()
		},
		macros = {};

	<?php if ($data['is_item_testable']) { ?>
	input_values = jQuery.extend(input_values, {
		get_value: jQuery('#get_value', $form).is(':checked') ? 1 : 0,
		host_proxy: jQuery('#host_proxy', $form).val(),
		interface: {
			address: jQuery('#interface_address', $form).val(),
			port: jQuery('#interface_port', $form).val()
		}
	});
	<?php } ?>

	<?php if ($data['show_prev']) { ?>
	input_values = jQuery.extend(input_values, {
		prev_value: jQuery('#prev_value').multilineInput('value'),
		prev_time: jQuery('#prev_time').val()
	});
	<?php } ?>

	jQuery('[name^=macros]').each(function(i, macro) {
		var name = macro.name.toString();
		macros[name.substr(7, name.length - 8)] = macro.value;
	});
	input_values.macros = macros;

	<?php if ($data['step_obj'] == -2) { ?>
		$test_obj = jQuery('.tfoot-buttons');
	<?php } elseif ($data['step_obj'] == -1) { ?>
		$test_obj = jQuery('preprocessing-list-foot', jQuery('#preprocessing'));
	<?php } else { ?>
		$test_obj = jQuery('.preprocessing-list-item[data-step=<?= $data['step_obj'] ?>]', jQuery('#preprocessing'));
	<?php } ?>

	$test_obj.data('test-data', input_values)
}

jQuery(document).ready(function($) {
	$('#final-result, #value-mapped-result').hide();

	<?php if ($data['show_prev']) { ?>
	jQuery('#upd_last').val(Math.ceil(+new Date()/1000));
	<?php } ?>

	$('#value').multilineInput({
		placeholder: <?= CJs::encodeJson(_('value')) ?>,
		value: <?= CJs::encodeJson($data['value']) ?>,
		monospace_font: false,
		maxlength: 65535,
		autofocus: true,
		readonly: false,
		grow: 'auto',
		rows: 0
	});

	$('#prev_value').multilineInput({
		placeholder: <?= $data['show_prev'] ? CJs::encodeJson(_('value')) : '""' ?>,
		value: <?= CJs::encodeJson($data['prev_value']) ?>,
		monospace_font: false,
		maxlength: 65535,
		disabled: <?= $data['show_prev'] ? 'false' : 'true' ?>,
		grow: 'auto',
		rows: 0
	});

	<?php if ($data['is_item_testable']) { ?>
	$('#get_value').on('change', function() {
		$rows = $('#host_address_row, #host_proxy_row, #get_value_row');
		if ($(this).is(':checked')) {
			<?php if ($data['proxies_enabled']) { ?>
			$('#host_proxy').prop('disabled', false);
			<?php } ?>

			<?php if ($data['interface_enabled']) { ?>
			$('#interface_address, #interface_port').prop('disabled', false);
			<?php } ?>

			$rows.show();
		}
		else {
			<?php if ($data['proxies_enabled']) { ?>
			$('#host_proxy').prop('disabled', true);
			<?php } ?>

			<?php if ($data['interface_enabled']) { ?>
			$('#interface_address, #interface_port').prop('disabled', false);
			<?php } ?>

			$rows.hide();
		}
	}).trigger('change');

	$('#get_value_btn').on('click', itemGetValueTest);
	<?php } ?>

	$('#preprocessing-test-form .<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>').textareaFlexible();
});

<?php return ob_get_clean(); ?>
