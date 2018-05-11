<?php 
$entities = json_decode(get_option('legal_entity'));
$cost_centers = json_decode(get_option('cost_center'));
$uda_approval = json_decode(get_option('uda_approval'));

$auth_cards = get_posts(array(
    'post_type'=>'auth_card',
    'post_status'=>'private',
    'posts_per_page'=>-1
    )
);

if(isset($_POST['filter'])) {
    foreach($entities as $key=>$value) {
        if(in_array($_POST['entity'], $value)) {
            $_POST['entity-type'] = $key;
        }
    }
}

if(isset($_POST['add-entity'])) {
    try {
        $entity = trim($_POST['entity']);
        $entity_type = $_POST['entity-type'];
        is_object($entities) ? $entities : $entities->$entity_type = array();
        is_array($entities->$entity_type) ? $entities->$entity_type : $entities->$entity_type = array();
        if(in_array($entity, $entities->$entity_type)) {
            throw new Exception("Entity exists!");
        } else {
            array_push($entities->$entity_type, $entity);
            update_option('legal_entity', json_encode($entities));
        }
        $success = "Legal Entity has been added.";
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

if(isset($_POST['add-new'])) {
    try {
        $duplicated_auth = get_posts(array(
            'post_type'=>'auth_card',
            'post_status'=>'private',
            'meta_query'=>array(
                array('key'=>'approval_type', 'value'=>$_POST['approval-type']),
                array('key'=>'uda_section', 'value'=>$_POST['uda-section']),
                array('key'=>'legal_entity', 'value'=>$_POST['entity']),
                array('key'=>'cost_center', 'value'=>$_POST['cost-center'])
            ),
            'posts_per_page'=>-1
            )
        );
        if(!empty($duplicated_auth)) {
            throw new Exception("Cost Center exists!");
        }
        $request_id = wp_insert_post(array(
            'post_type'=>'auth_card',
            'post_title'=>'Auth Card',
            'post_status'=>'private'
        ));
        add_post_meta($request_id, 'legal_entity', $_POST['entity']);
        add_post_meta($request_id, 'uda_section', $_POST['uda-section']);
        add_post_meta($request_id, 'approval_type', $_POST['approval-type']);
        add_post_meta($request_id, 'cost_center', trim($_POST['cost-center']));
        add_post_meta($request_id, 'cost_center_description', trim($_POST['description']));
        $success = "Cost Center has been added!";
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

if(isset($_POST['change-request'])) {
    try {
        $requests = get_posts(array(
            'post_type'=>'auth_card',
            'post_status'=>'private',
            'meta_query'=>array(
                array('key'=>'approval_type', 'value'=>$_POST['approval-type']),
                array('key'=>'uda_section', 'value'=>$_POST['uda-section']),
                array('key'=>'legal_entity', 'value'=>$_POST['entity']),
                array('key'=>'cost_center', 'value'=>$_POST['cost-center'], 'compare'=>'IN')
            ),
            'posts_per_page'=>-1
            )
        );
        foreach($requests as $request) {
            if(!empty($_POST['approver-for-' . $request->ID])) {
                update_post_meta($request->ID, $_POST['approval'], $_POST['approver-for-' . $request->ID]);
            }
        }
        $success = "Change request submitted.";
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<style>
.inline {
    display: inline-block;
    margin-left: 50px;
}
.add-new {
    margin-bottom: 10px;
}
.basic {
    min-height: 470px;
}
#comments {
    width: 84%;
}
.lbjs {
    width: 250px;
}
.modal form {
	margin-bottom: 0;
}
.change-request-modal {
    width: 1100px;
    margin-left: -550px;
}
.table-view th, .table-view td {
    text-align: center;
    vertical-align: middle;
}
.position {
    background-color: #23282D;
    color: #FFF;
}
.level {
    background-color: #E1E1E1;
}
.no-border {
    border-top: none !important;
}
</style>

<div class="site-content box" role="main">
	<header>
        <?php the_title(); ?>
    </header>
	<div class="content">
        <?php if($error) { ?>
        <div class="alert alert-error"><?=$error?></div>
        <?php } ?>
        <?php if($success) { ?>
        <div class="alert alert-success"><?=$success?></div>
        <?php } ?>
		<div class="row-fluid">
            <form method="post" class="form-inline pull-left">
                <select name="entity" id="entity-selected">
                    <?php foreach($entities as $entity_type) { ?>
                        <?php foreach($entity_type as $entity) { ?>
                        <option value="<?=$entity?>" <?php if(isset($_POST['filter']) && $_POST['entity'] === $entity) { ?>selected<?php } ?>><?=$entity?></option>
                        <?php } ?>
                    <?php } ?>
                </select>
                <select name="uda-section" id="uda-section-selected">
                    <option value="CapEx" <?php if(isset($_POST['filter']) && $_POST['uda-section'] === 'CapEx') { ?>selected<?php } ?>>CapEx</option>
                    <option value="Non-CapEx" <?php if(isset($_POST['filter']) && $_POST['uda-section'] === 'Non-CapEx') { ?>selected<?php } ?>>Non-CapEx</option>
                    <option value="Employee Expense" <?php if(isset($_POST['filter']) && $_POST['uda-section'] === 'Employee Expense') { ?>selected<?php } ?>>Employee Expense</option>
                </select>
                <select name="approval-type" id="approval-type-selected">
                    <option value="Operation Approval" <?php if(isset($_POST['filter']) && $_POST['approval-type'] === 'Operation Approval') { ?>selected<?php } ?>>Operation Approval</option>
                    <option value="Finance Approval" <?php if(isset($_POST['filter']) && $_POST['approval-type'] === 'Finance Approval') { ?>selected<?php } ?>>Finance Approval</option>
                </select>
                <button type="submit" id="filter" name="filter" class="btn">Filter</button>
            </form>
            <button type="button" onclick="addEntity()" class="btn btn-primary pull-right">Add Legal Entity</button>
            <?php if(isset($_POST['filter'])) { ?>
            <table class="table table-bordered table-view">
                <tbody>
                    <tr>
                        <th colspan="2"><?=$_POST['approval-type']?></th>
                        <th class="no-border"></th>
                        <?php foreach($uda_approval as $approval) { ?>
                            <?php if($approval->approval_type === $_POST['approval-type'] && $approval->uda_section === $_POST['uda-section'] && $approval->entity_type === $_POST['entity-type']) { ?>
                                <?php foreach($approval->approval as $position) { ?>
                        <th class="position"><?=$position?></th>
                                <?php } ?>
                            <?php } ?>
                        <?php } ?>
                    </tr>
                    <tr>
                        <th>Cost Center Description</th>
                        <th>Cost Center</th>
                        <th class="no-border"></th>
                        <th class="level" colspan="5">Reginonal Level</th>
                    </tr>
                    <?php foreach($auth_cards as $auth_card) { ?>
                        <?php if($auth_card->approval_type === $_POST['approval-type'] && $auth_card->uda_section === $_POST['uda-section'] && $auth_card->legal_entity === $_POST['entity']) { ?>
                    <tr>
                        <td><?=$auth_card->cost_center_description?></td>
                        <td><?=$auth_card->cost_center?></td>
                        <td class="no-border"></td>
                        <?php foreach($uda_approval as $approval) { ?>
                            <?php if($approval->approval_type === $_POST['approval-type'] && $approval->uda_section === $_POST['uda-section'] && $approval->entity_type === $_POST['entity-type']) { ?>
                                <?php foreach($approval->approval as $position) { ?>
                        <td><?=$auth_card->$position?></td>
                                <?php } ?>
                            <?php } ?>
                        <?php } ?>
                    </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
            <button type="button" onclick="addNew()" class="btn btn-success">Add New</button>
            <button type="button" onclick="changeRequest()" class="btn btn-success pull-right">Change</button>                        
            <?php } ?>
        </div>
    </div>
</div>

<div class="modal-container">
    <div class="add-entity-modal modal hide fade">
        <form method="post" class="form-horizontal">
            <div class="modal-header">
                <h3 class="title">Add Legal Entity</h3>
            </div>
            <div class="modal-body">
                <div class="control-group">
                    <label class="control-label">Legal Entity</label>
                    <div class="controls">
                        <input type="text" name="entity" required>
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label">RGO & BU/NSC</label>
                    <div class="controls">
                        <select name="entity-type">
                            <option value="RGO & BU">RGO & BU</option>
                            <option value="NSC">NSC</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn close-modal">Close</button>
                <button type="submit" name="add-entity" class="btn btn-primary">Add Legal Entity</button>
            </div>
        </form>
    </div>
    <div class="request-modal modal hide fade">
        <form method="post" class="form-horizontal">
            <div class="modal-header">
                <h3 class="title">Change Request</h3>
            </div>
            <div class="modal-body">
                <div class="control-group">
                    <label class="control-label">Cost Center</label>
                    <div class="controls">
                        <select id="cost-center-for-change-request" name="cost-center[]" multiple required>
                            <?php foreach($auth_cards as $auth_card) { ?>
                                <?php if($auth_card->approval_type === $_POST['approval-type'] && $auth_card->uda_section === $_POST['uda-section'] && $auth_card->legal_entity === $_POST['entity']) { ?>
                            <option value="<?=$auth_card->cost_center?>"><?=$auth_card->cost_center?> [<?=$auth_card->cost_center_description?>]</option>
                                <?php } ?>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label">Approval</label>
                    <div class="controls">
                        <select name="approval" id="approval-selected" required> 
                            <?php foreach($uda_approval as $approval) { ?>
                                <?php if($approval->approval_type === $_POST['approval-type'] && $approval->uda_section === $_POST['uda-section'] && $approval->entity_type === $_POST['entity-type']) { ?>
                                    <?php foreach($approval->approval as $position) { ?>
                            <option value="<?=$position?>"><?=$position?></option>
                                    <?php } ?>
                                <?php } ?>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn close-modal">Close</button>
                <button type="button" onclick="changeRequestDetail()" class="btn btn-success">Next</button>
            </div>
        </form>
    </div>
    <div class="change-request-modal modal hide fade">
        <form method="post" class="form-horizontal">
            <div class="modal-header">
                <h3 class="title">Change Request</h3>
            </div>
            <div class="modal-body">
                
            </div>
            <div class="modal-footer">
                <button type="button" class="btn close-modal">Close</button>
                <button type="submit" name="change-request" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </div>
    <div class="add-new-modal modal hide fade">
        <form method="post" class="form-horizontal">
            <div class="modal-header">
                <h3 class="title">Add New</h3>
            </div>
            <div class="modal-body">
                <div class="control-group">
                    <label class="control-label">Cost Center</label>
                    <div class="controls">
                        <input type="text" name="cost-center" required>
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label">Cost Center Description</label>
                    <div class="controls">
                        <input type="text" name="description" required>
                    </div>
                </div>
                <div>
                    <input type="hidden" name="entity" value="<?=$_POST['entity']?>">
                    <input type="hidden" name="uda-section" value="<?=$_POST['uda-section']?>">
                    <input type="hidden" name="approval-type" value="<?=$_POST['approval-type']?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn close-modal">Close</button>
                <button type="submit" name="add-new" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(function($) {
    $('.modal-container').on('click', 'button.close-modal', function() {
		$(this).closest('.modal').modal('hide');
	});
    $('input.date').datetimepicker({
        autoclose: true,
        startView: 'month',
        minView: 'month',
        format: 'yyyy-mm-dd'
    });
    $('.user-name').on('focus', function() {
		$(this).siblings('.label').show(200);
	}).on('blur', function() {
		$(this).siblings('.label').hide(200);
	}).typeahead({
		source: function(query, process) {
			$.get('<?=site_url()?>/user?s_user=' + query, function(result) {
				process(result);
			}) 
		},
		minLength: 2,
		highlighter: function(item) {
			return item;
		}
	});
    window.addEntity = function() {
        $('.add-entity-modal').modal('show');
    }
    window.changeRequest = function() {
        $('.request-modal').modal('show');
    }
    window.changeRequestDetail = function() {
        $('.request-modal').modal('hide');
        $('.change-request-modal').modal('show');
    }
    window.addNew = function() {
        $('.add-new-modal').modal('show');
    }
    // $('.table-view td').each(function() {
    //     $this = $(this);
    //     col = $this.index();                
    //     txt =  $this.text();                
    //     // row = $(this).parent()[0].rowIndex; 
    //     span = 1;
    //     cell_above = $($this.parent().prev().children()[col]);

    //     //look for cells one above another with the same text
    //     while(cell_above.text() === txt) {                    //if the text is the same
    //         span += 1;                                        //increase the span
    //         cell_above_old = cell_above;                    //store this cell
    //         cell_above = $(cell_above.parent().prev().children()[col]);    //and go to the next cell above
    //     }

    //     //if there are at least two columns with the same value, set a new span to the first and hide the other
    //     if(span > 1) {
    //         $(cell_above_old).attr('rowspan', span); 
    //         $this.hide();
    //     }              
    // });
    $('select#cost-center-for-change-request').listbox();
    $('#approval-selected, #cost-center-for-change-request').change(function() {
        entity = $('#entity-selected').val();
        uda_section = $('#uda-section-selected').val();
        approval_type = $('#approval-type-selected').val();
        approval = $('#approval-selected').val();
        cost_centers = $('#cost-center-for-change-request').val();
        $.post(
            '<?=site_url()?>/change-request/',
            {
                entity: entity,
                section: uda_section,
                type: approval_type,
                approval: approval,
                cost_centers: cost_centers
            },
            function(data) {
                $html = $(".change-content", data);
                $('.change-request-modal .modal-body').html($html);
            }
        )
    });
});
</script>