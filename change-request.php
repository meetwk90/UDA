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
            <tr class="table-condensed">
                <td>
                    <?php if($_POST['section'] !== 'Employee Expense') { ?>
                    <label>PR Approval</label>
                    <?php } ?>
                </td>
                <td style="border-left:none;border-bottom:none">
                    <?php if($_POST['section'] !== 'Employee Expense') { ?>
                    <label>Authorization Level</label>
                    <?php } ?>
                </td>
                <td style="border-bottom:none">
                    <?php if($_POST['section'] === 'Employee Expense') { ?>
                    <label>CONCUR Authorization Level</label>
                    <?php } else { ?>
                    <label>Payment Request Signing<br>Authorization Level</label>
                    <?php } ?>
                </td>
                <td>
                    <label>Name of approver</label>
                </td>
            </tr>
            <tr class="table-condensed">
                <td style="border-top:none;width:250px">
                    <?php if($_POST['section'] !== 'Employee Expense') { ?> 
                    <label><?=$pr_approval_category[0]?></label>
                    <?php } ?>
                </td>
                <td style="border:none">
                    <?php if($_POST['section'] !== 'Employee Expense') { ?> 
                    <div class="input-prepend">
                        <span class="add-on">$</span>
                        <?php $pr_title = 'pr_auth_level-' . $_POST['approval']; ?>
                        <input type="number" name="pr[<?=$auth_card->ID?>][<?=$pr_approval_category[0]?>]" value="<?=json_decode($auth_card->$pr_title)->$pr_approval_category[0]?>" style="width:180px" readonly>
                    </div>
                    <button type="button" class="btn btn-success btn-small edit-pr-approval">Edit</button>
                    <?php } ?>
                </td>
                <td style="border-top:none">
                    <div class="input-prepend">
                        <span class="add-on">$</span>
                        <?php if($_POST['section'] === 'Employee Expense') { ?> 
                            <?php $concur_title = 'concur_auth_level-' . $_POST['approval']; ?>
                        <input type="number" name="concur[<?=$auth_card->ID?>]" value="<?=$auth_card->$concur_title?>" style="width:180px" readonly>
                        <?php } else { ?>
                            <?php $payment_title = 'payment_auth_level-' . $_POST['approval']; ?>
                        <input type="number" name="payment[<?=$auth_card->ID?>]" value="<?=$auth_card->$payment_title?>" style="width:180px" readonly>
                        <?php } ?>
                    </div>
                    <button type="button" class="btn btn-success btn-small edit-payment">Edit</button>
                </td>
                <td style="border-top:none">
                    <input type="text" name="approver[<?=$auth_card->ID?>]" value="<?=$auth_card->$_POST['approval']?>" readonly>
                    <button type="button" class="btn btn-success btn-small edit-approver">Edit</button>
                </td>
            </tr>
            <?php if($_POST['section'] !== 'Employee Expense') { ?> 
            <tr class="table-condensed">
                <td style="border-top:none;width:250px">    
                    <label><?=$pr_approval_category[1]?></label>
                </td>
                <td style="border-top:none;border-left:none">
                    <div class="input-prepend">
                        <span class="add-on">$</span>
                        <input type="number" name="pr[<?=$auth_card->ID?>][<?=$pr_approval_category[1]?>]" value="<?=json_decode($auth_card->$pr_title)->$pr_approval_category[1]?>" style="width:180px" readonly>
                    </div>
                    <button type="button" class="btn btn-success btn-small edit-pr-approval">Edit</button>
                </td>
                <td style="border-top:none"></td>
                <td style="border-top:none"></td>
            </tr>
            <tr class="table-condensed">
                <td style="border-top:none;width:250px">
                    <label><?=$pr_approval_category[2]?></label>
                </td>
                <td style="border-top:none;border-left:none">
                    <div class="input-prepend">
                        <span class="add-on">$</span>
                        <input type="number" name="pr[<?=$auth_card->ID?>][<?=$pr_approval_category[2]?>]" value="<?=json_decode($auth_card->$pr_title)->$pr_approval_category[2]?>" style="width:180px" readonly>
                    </div>
                    <button type="button" class="btn btn-success btn-small edit-pr-approval">Edit</button>
                </td>
                <td style="border-top:none"></td>
                <td style="border-top:none"></td>
            </tr>
            <tr class="table-condensed">
                <td style="border-top:none;width:250px">
                    <label><?=$pr_approval_category[3]?></label>
                </td>
                <td style="border-top:none;border-left:none">
                    <div class="input-prepend">
                        <span class="add-on">$</span>
                        <input type="number" name="pr[<?=$auth_card->ID?>][<?=$pr_approval_category[3]?>]" value="<?=json_decode($auth_card->$pr_title)->$pr_approval_category[3]?>" style="width:180px" readonly>
                    </div>
                    <button type="button" class="btn btn-success btn-small edit-pr-approval">Edit</button>
                </td>
                <td style="border-top:none"></td>
                <td style="border-top:none"></td>
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
            $(this).css('opacity', 0).prev().attr('readonly', false);
        });
        $('.edit-payment, .edit-pr-approval').click(function() {
            $(this).css('opacity', 0).prev().children().attr('readonly', false);
        });
    });
    </script>
</div>