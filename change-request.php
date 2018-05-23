<?php 
$pr_approval_category = json_decode(get_option('pr_approval_category'));
$auth_cards = get_posts(array(
    'post_type'=>'auth_card',
    'post_status'=>'private',
    'meta_query'=>array(
        array('key'=>'approval_type', 'value'=>$_POST['type']),
        array('key'=>'uda_section', 'value'=>$_POST['section']),
        array('key'=>'legal_entity', 'value'=>$_POST['entity']),
        array('key'=>'cost_center', 'value'=>$_POST['cost_centers'], 'compare'=>'IN')
    ),
    'posts_per_page'=>-1
    )
);
?>
<div class="change-content">
    <?php foreach($auth_cards as $auth_card) { ?>
    <input type="hidden" name="ID[]" value="<?=$auth_card->ID?>" />
    <table class="table table-bordered">
        <tbody>
            <tr>
                <td>
                    <label>Entity</label>
                    <input type="text" value="<?=$_POST['entity']?>" readonly>
                </td>
                
                <td style="border-left:none">
                    <label>UDA Section</label>
                    <input type="text" value="<?=$_POST['section']?>" readonly>
                </td>
                <td style="border-left:none">
                    <label>Operation/Finance Approval</label>
                    <input type="text" value="<?=$_POST['type']?>" readonly>
                </td>
                <td style="border-left:none">
                    <label>Cost Center</label>
                    <input style="width:250px" type="text" value="<?=$auth_card->cost_center?> [<?=$auth_card->cost_center_description?>]" readonly>
                </td>
            </tr>
            <?php if($_POST['section'] !== 'Employee Expense') { ?> 
            <tr class="table-condensed">
                <td colspan="4">
                    <label>PR Approval Authorization Level</label>
                </td>
            </tr>
            <tr class="table-condensed">
                <td style="border-top:none">
                    <label><?=$pr_approval_category[0]?></label>
                    <div class="input-prepend">
                        <span class="add-on">$</span>
                        <?php $pr_title = 'pr_auth_level-' . $_POST['approval']; ?>
                        <input type="number" name="pr[<?=$auth_card->ID?>][<?=$pr_approval_category[0]?>]" value="<?=json_decode($auth_card->$pr_title)->$pr_approval_category[0]?>" style="width:180px" readonly>
                    </div>
                    <button type="button" class="btn btn-success btn-small edit-pr-approval">Edit</button>
                </td>
                <td style="border-top:none;border-left:none">    
                    <label><?=$pr_approval_category[1]?></label>
                    <div class="input-prepend">
                        <span class="add-on">$</span>
                        <input type="number" name="pr[<?=$auth_card->ID?>][<?=$pr_approval_category[1]?>]" value="<?=json_decode($auth_card->$pr_title)->$pr_approval_category[1]?>" style="width:180px" readonly>
                    </div>
                    <button type="button" class="btn btn-success btn-small edit-pr-approval">Edit</button>
                </td>
                <td style="border-top:none;border-left:none">
                    <label><?=$pr_approval_category[2]?></label>
                    <div class="input-prepend">
                        <span class="add-on">$</span>
                        <input type="number" name="pr[<?=$auth_card->ID?>][<?=$pr_approval_category[2]?>]" value="<?=json_decode($auth_card->$pr_title)->$pr_approval_category[2]?>" style="width:180px" readonly>
                    </div>
                    <button type="button" class="btn btn-success btn-small edit-pr-approval">Edit</button>
                </td>
                <td style="border-top:none;border-left:none">
                    <label><?=$pr_approval_category[3]?><br>&nbsp;</label>
                    <div class="input-prepend">
                        <span class="add-on">$</span>
                        <input type="number" name="pr[<?=$auth_card->ID?>][<?=$pr_approval_category[3]?>]" value="<?=json_decode($auth_card->$pr_title)->$pr_approval_category[3]?>" style="width:180px" readonly>
                    </div>
                    <button type="button" class="btn btn-success btn-small edit-pr-approval">Edit</button>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <label>Payment Request Signing Authorization Level</label>
                    <div class="input-prepend">
                        <span class="add-on">$</span>
                            <?php $payment_title = 'payment_auth_level-' . $_POST['approval']; ?>
                        <input type="number" name="payment[<?=$auth_card->ID?>]" value="<?=$auth_card->$payment_title?>" style="width:180px" readonly>
                    </div>
                    <button type="button" class="btn btn-success btn-small edit-payment">Edit</button>
                </td>
                <td colspan="2" class="approvers">
                    <label>Name of approver</label>
                    <?php if(is_array(json_decode($auth_card->$_POST['approval']))) { ?>
                        <?php foreach(json_decode($auth_card->$_POST['approval']) as $approver) { ?>
                    <div class="approver" style="margin-bottom:5px">
                        <input type="text" name="approver[<?=$auth_card->ID?>][]" value="<?=$approver?>" readonly>
                            <?php if($_POST['approval'] !== 'Group CEO' && $_POST['approval'] !== 'Group CFO') { ?>
                        <button type="button" class="btn btn-success btn-small edit-approver">Edit</button>
                            <?php } ?>
                        <button type="button" class="btn remove-approver hide"><i class="icon-minus"></i></button>
                    </div>
                        <?php } ?>
                    <?php } else { ?>
                    <div class="approver" style="margin-bottom:5px">
                        <input type="text" name="approver[<?=$auth_card->ID?>][]" readonly>
                            <?php if($_POST['approval'] !== 'Group CEO' && $_POST['approval'] !== 'Group CFO') { ?>
                        <button type="button" class="btn btn-success btn-small edit-approver">Edit</button>
                            <?php } ?>
                        <button type="button" class="btn remove-approver hide"><i class="icon-minus"></i></button>
                    </div>
                    <?php } ?>
                    <?php if($_POST['approval'] !== 'Group CEO' && $_POST['approval'] !== 'Group CFO') { ?>
                    <button type="button" class="btn add-approver"><i class="icon-plus"></i></button>
                    <?php } ?>
                </td>
            </tr>
            <?php } else { ?>
            <tr>
                <td colspan="2">
                    <label>CONCUR Authorization Level</label>
                    <div class="input-prepend">
                        <span class="add-on">$</span>
                            <?php $concur_title = 'concur_auth_level-' . $_POST['approval']; ?>
                        <input type="number" name="concur[<?=$auth_card->ID?>]" value="<?=$auth_card->$concur_title?>" style="width:180px" readonly>
                    </div>
                    <button type="button" class="btn btn-success btn-small edit-concur">Edit</button>
                </td>
                <td colspan="2" class="approvers">
                    <label>Name of approver</label>
                    <?php if(is_array(json_decode($auth_card->$_POST['approval']))) { ?>
                        <?php foreach(json_decode($auth_card->$_POST['approval']) as $approver) { ?>
                    <div class="approver" style="margin-bottom:5px">
                        <input type="text" name="approver[<?=$auth_card->ID?>][]" value="<?=$approver?>" readonly>
                            <?php if($_POST['approval'] !== 'Group CEO' && $_POST['approval'] !== 'Group CFO') { ?>
                        <button type="button" class="btn btn-success btn-small edit-approver">Edit</button>
                            <?php } ?>
                        <button type="button" class="btn remove-approver hide"><i class="icon-minus"></i></button>
                    </div>
                        <?php } ?>
                    <?php } else { ?>
                    <div class="approver" style="margin-bottom:5px">
                        <input type="text" name="approver[<?=$auth_card->ID?>][]" readonly>
                            <?php if($_POST['approval'] !== 'Group CEO' && $_POST['approval'] !== 'Group CFO') { ?>
                        <button type="button" class="btn btn-success btn-small edit-approver">Edit</button>
                            <?php } ?>
                        <button type="button" class="btn remove-approver hide"><i class="icon-minus"></i></button>
                    </div>
                    <?php } ?>
                    <?php if($_POST['approval'] !== 'Group CEO' && $_POST['approval'] !== 'Group CFO') { ?>
                    <button type="button" class="btn add-approver"><i class="icon-plus"></i></button>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
    <?php } ?>
    <input type="hidden" name="approval" value="<?=$_POST['approval']?>">
    <input type="hidden" name="uda-section" value="<?=$_POST['section']?>" />
    <script>
    jQuery(function($) {
        $('.edit-approver').click(function() {
            $(this).prev().attr('readonly', false).next().next().show();
            $(this).remove();
        });
        $('.edit-payment, .edit-concur, .edit-pr-approval').click(function() {
            $(this).prev().children().attr('readonly', false);
            $(this).remove();
        });
        $('.add-approver').click(function() {
            if($(this).prev().hasClass('approver')) {
                cloned = $(this).prev();
            }
            cloned.clone().find('input').val('').attr('readonly', false).end().find('.edit-approver').hide().end().find('.remove-approver').show().end().insertAfter(cloned);
        });
        $('.approvers').on('click', '.remove-approver', function() {
            $(this).closest('.approver').remove();
        });
    });
    </script>
</div>