<?php 
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
    <table class="table table-bordered">
        <tbody>
            <tr>
                <td>
                    <label>Entity</label>
                    <input type="text" name="entity-for-<?=$auth_card->cost_center?>" value="<?=$_POST['entity']?>" readonly>
                </td>
                <td style="border-left:none">
                    <label>Cost Center Description</label>
                    <input type="text" value="<?=$auth_card->cost_center_description?>" readonly>
                </td>
                <td style="border-left:none">
                    <label>Cost Center Number</label>
                    <input name="cost-center[]" type="text" value="<?=$auth_card->cost_center?>" readonly>
                </td>
                <td style="border-left:none">
                    <label>Operation/Finance Approval</label>
                    <input type="text" name="approval-type-for-<?=$auth_card->cost_center?>" value="<?=$_POST['type']?>" readonly>
                    <input type="hidden" name="uda-section-for-<?=$auth_card->cost_center?>" value="<?=$_POST['section']?>">
                </td>
            </tr>
            <tr class="table-condensed">
                <td>
                    <label>PR Approval</label>
                </td>
                <td style="border-left:none;border-bottom:none">
                    <label>Authorization Level</label>
                </td>
                <td style="border-bottom:none">
                    <label>Payment Request Signing<br>Authorization Level</label>
                </td>
                <td>
                    <label>CONCUR<br>Authorization Level</label>
                </td>
            </tr>
            <tr class="table-condensed">
                <td style="border-top:none">
                    <label><input type="checkbox" id="1" name="PR" value="1"> category 1</label>
                </td>
                <td style="border:none">
                    <div class="input-prepend">
                        <span class="add-on">$</span>
                        <input type="number" id="for1" name="authorization-level[]" style="width:180px" disabled>
                    </div>
                </td>
                <td style="border-top:none">
                    <div class="input-prepend">
                        <span class="add-on">$</span>
                        <input type="number" name="authorization-level[]" style="width:180px" disabled>
                    </div>
                </td>
                <td style="border-top:none">
                    <div class="input-prepend">
                        <span class="add-on">$</span>
                        <input type="number" name="authorization-level[]" style="width:180px" disabled>
                    </div>
                </td>
            </tr>
            <tr class="table-condensed">
                <td style="border-top:none">    
                    <label><input type="checkbox" name="PR" value="2"> category 2</label>
                </td>
                <td style="border-top:none;border-left:none">
                    <div class="input-prepend">
                        <span class="add-on">$</span>
                        <input type="number" name="authorization-level[]" style="width:180px" disabled>
                    </div>
                </td>
                <td></td>
                <td style="border-left:none"></td>
            </tr>
            <tr class="table-condensed">
                <td style="border-top:none">
                    <label><input type="checkbox" name="PR" value="3"> category 3</label>
                </td>
                <td style="border-top:none;border-left:none">
                    <div class="input-prepend">
                        <span class="add-on">$</span>
                        <input type="number" name="authorization-level[]" style="width:180px" disabled>
                    </div>
                </td>
                <td style="border-top:none"></td>
                <td style="border-top:none;border-left:none"></td>
            </tr>
            <tr class="table-condensed">
                <td style="border-top:none">
                    <label><input type="checkbox" name="PR" value="4"> category 4</label>
                </td>
                <td style="border-top:none;border-left:none">
                    <div class="input-prepend">
                        <span class="add-on">$</span>
                        <input type="number" name="authorization-level[]" style="width:180px" disabled>
                    </div>
                </td>
                <td style="border-top:none">
                    <label class="pull-right">Name of approver</label>
                </td>
                <td style="border-top:none;border-left:none">
                    <input type="text" name="approver-for-<?=$auth_card->ID?>" value="<?=$auth_card->$_POST['approval']?>" disabled>
                    <button type="button" class="btn btn-success btn-small edit-approver">Edit</button>
                </td>
            </tr>
        </tbody>
    </table>
    <?php } ?>
    <input type="hidden" name="approval" value="<?=$_POST['approval']?>">
    <input type="hidden" name="approval-type" value="<?=$_POST['type']?>">
    <input type="hidden" name="uda-section" value="<?=$_POST['section']?>">
    <input type="hidden" name="entity" value="<?=$_POST['entity']?>">
    <script>
    jQuery(function($) {
        $('.edit-approver').click(function() {
            $(this).css('opacity', 0).prev().val('').attr('disabled', false);
        });
    });
    </script>
</div>