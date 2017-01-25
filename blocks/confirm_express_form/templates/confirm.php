<?php defined('C5_EXECUTE') or die("Access Denied.");
use Symfony\Component\HttpFoundation\Session\Session;
$nh = Core::make('helper/navigation');
$c = \Page::getCurrentPage();

/* ===============================================
エラーメッセージを分解
=============================================== */
if (isset($error) && is_object($error)) {
	$errors = [];
	foreach ($error->getList() as $e) {
		$field = $e->getField();
		if (is_object($field)) {
			$errors[$field->getDisplayName()] = $e->getMessage();
		}
	}
}
/* ===============================================
メッセージ分岐
=============================================== */
if($mode == 'confirm'){
	$submitLabel = $sendLabel;
}
//var_dump($form_session_data);
?>
<a name="form<?php echo $bID?>" id="form<?php echo $bID?>" class="anch"></a>
<?php if (isset($success)) { ?>
	<div class="alert alert-success">
		<?php echo $success?>
	</div>
<?php } ?>

<form id="entryForm" enctype="multipart/form-data" class="form-stacked wg_entry_form" method="post" action="<?php echo $view->action('submit')?>#form<?php echo $bID?>">
<?php
	if(!$mode){
		$form_utilities['mode'] = 'confirm';
	}elseif($mode == 'confirm'){
		$form_utilities['mode'] = 'send';
	}

	if($form_utilities && $form_controls){
		echo '<table>';
		foreach($form_utilities as $fukey => $fuval){
			$fh = Core::make('helper/form');
			echo '<input type="hidden" name="'.$fukey.'" value="'.$fuval.'">';
		}
		foreach($form_controls as $control){
			if($control->isRequired){
				$req = '<span class="entry_req">※必須</span>';
			} else{
				$req = '';
			}
			echo '<tr>';
			echo '<th><label>'.$control->question.$req.'</label></th>';

			if($mode == 'confirm' && $form_id == $sessions_id){//確認画面
				if($form_session_data[$control->keyID]){
					foreach($form_session_data[$control->keyID] as $fkey => $fval){
						if($fkey == 'value'){
							echo '<td>'.$fval;
							echo '<input type="hidden" name="akID['.$control->keyID.'][value]" value="'.$fval.'">';
							echo '</td>';
						}elseif($fkey == 'atSelectOptionValue'){
							$values = [];
							$inputs = '';

							$option_list = explode(',', $fval);
							if(count($option_list) > 1){
								foreach($option_list as $option){
									$values[] = $control->options[$option];
									$inputs .= '<input type="hidden" name="akID['.$control->keyID.'][atSelectOptionValue][]" value="'.$option.'">';
								}
							}else{
								$values[] = $control->options[$fval];
								$inputs = '<input type="hidden" name="akID['.$control->keyID.'][atSelectOptionValue]" value="'.$fval.'">';
							}
							echo '<td>';
							echo implode(',', $values);
							echo $inputs;
							echo '</td>';
						}elseif($fkey == 'file'){
							echo '<td><img src="'.$fval['src'].'" alt="" style="max-width:150px;">';
							echo '<input type="hidden" name="akID['.$control->keyID.'][value][tmp_name]" value="'.$fval['tmp_name'].'">';
							echo '<input type="hidden" name="akID['.$control->keyID.'][value][origin_name]" value="'.$fval['origin_name'].'">';
							echo '</td>';
						}
					}
				}
			}else{//入力画面
				echo '<td>'.$control->typeContent.'<span class="error_msg">'.$errors[trim($control->question)].'</span></td>';
			}
			echo '</tr>';
		}
		echo '</table>';
	}

if ($displayCaptcha) {
	$captcha = \Core::make('helper/validation/captcha');
	?>
	<div class="form-group captcha">
		<?php
		$captchaLabel = $captcha->label();
		if (!empty($captchaLabel)) {
			?>
			<label class="control-label"><?php echo $captchaLabel;
				?></label>
			<?php

		}
		?>
		<div><?php $captcha->display(); ?></div>
		<div><?php $captcha->showInput(); ?></div>
	</div>
<?php } ?>

<div class="form-actions">
	<button type="submit" name="Submit" class="entry_submit_btn"><?php echo t($submitLabel)?></button>
	<?php if($mode == 'confirm'):?>
		<input class="entry_back_btn" type="button" value="戻る" onClick="location.href='<?php echo $nh->getLinkToCollection($c).'#form'.$bID; ?>'">　
	<?php endif;?>
</div>
</form>

<?php
/* ===============================================
戻るボタン押下時のformへのデータバインド
=============================================== */
if($form_session_data && $form_id == $sessions_id && !$mode):?>
<script>
$(document).ready(function(){
	$(function() {
		var form_data_<?php echo $bID;?> = <?php echo $form_session_data_json;?>;
		var bind_form_data_<?php echo $bID;?> = function(form_data){
			_.each(form_data,function(value, key){
				if(value.value){
					$('[name="akID['+key+'][value]"]').val(value.value);
				}else if(value.file){
					var input_class = key+'file_hidden';
					var base_input = $('[name="akID['+key+'][value]"]');
					var file_data = {
						img :$('<img />').addClass(input_class).attr('src',value.file.src).css({'max-width':'150px','height':'auto'}),
						tmp_name :$('<input type="hidden" name="akID['+key+'][file][tmp_name]" />').addClass(input_class).val(value.file.tmp_name),
						src :$('<input type="hidden" name="akID['+key+'][file][src]" />').addClass(input_class).val(value.file.src),
						origin_name :$('<input type="hidden" name="akID['+key+'][file][origin_name]" />').addClass(input_class).val(value.file.origin_name),
						extention :$('<input type="hidden" name="akID['+key+'][file][extention]" />').addClass(input_class).val(value.file.extention),
					}
					_.each(file_data,function(item){
						base_input.before(item);
					});
					base_input.remove();

					var append_input = $('.' + input_class);
					append_input.last().after($('<a href="'+input_class+'" class="delete_img" style="display:block;" />').text('画像を削除する'));
					$('a.delete_img').on('click',function(e){
						e.preventDefault();
						append_input.last().after(base_input);

						append_input.remove();
						$(this).remove();
					});
				}else if(value.atSelectOptionValue){
					var options = value.atSelectOptionValue.split(',')
					_.each(options,function(option){
						$('[name="akID['+key+'][atSelectOptionValue][]"]').each(function(){
							if($(this).val() == option){
								$(this).prop('checked',true);
							}
						});

						if($('[name="akID['+key+'][atSelectOptionValue]"]').length > 0){
							_.each($('[name="akID['+key+'][atSelectOptionValue]"]').find('option'),function(item){
								if($(item).val() == option){
									$(item).prop('selected',true);
								}
							})
						}
					});
				}
			});
		};

		bind_form_data_<?php echo $bID;?>(form_data_<?php echo $bID;?>);
	});
});
</script>
<?php endif;?>