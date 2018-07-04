<?php 

$entities = json_decode(get_option('legal_entity'));
$cost_centers = json_decode(get_option('cost_center'));
$uda_approval = json_decode(get_option('uda_approval'));
$company_leaders = json_decode(get_option('company_leaders'));

$change_requests = get_posts(array(
    'post_type'=>'auth_change_request',
    'post_status'=>'private',
    'meta_query'=>array(
        array('key'=>'requestor_id', 'value'=>get_current_user_id()),
        array('key'=>'status', 'value'=>'Waiting for submission')
    ),
    'posts_per_page'=>-1
    )          
);

if(isset($_POST['filter'])) {

    foreach($entities as $key=>$value) {
        if(in_array($_POST['entity'], $value)) {
            $_POST['entity-type'] = $key;
        }
    }

    foreach($uda_approval as $approval) {
        if($approval->entity_type === $_POST['entity-type'] && $approval->uda_section === $_POST['uda-section'] && $approval->approval_type === $_POST['approval-type']) {
            $positions = $approval->approval;
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

        foreach($_POST['uda-section'] as $section) {

            $duplicated_auth = get_posts(array(
                'post_type'=>'auth_card',
                'post_status'=>'private',
                'meta_query'=>array(
                    array('key'=>'uda_section', 'value'=>$section),
                    array('key'=>'legal_entity', 'value'=>$_POST['entity']),
                    array('key'=>'cost_center', 'value'=>$_POST['cost-center'])
                ),
                'posts_per_page'=>-1
                )
            );

            if(!empty($duplicated_auth)) {
                throw new Exception("Cost Center exists!");
            }

            foreach(array('Operation Approval', 'Finance Approval') as $approval_type) {
                
                $request_id = wp_insert_post(array(
                    'post_type'=>'auth_card',
                    'post_title'=>'Auth Card',
                    'post_status'=>'private'
                ));

                foreach($entities as $key=>$value) {

                    if(in_array($_POST['entity'], $value)) {
                        $entity_type = $key;
                    }

                    foreach($uda_approval as $approval) {
                        if($approval->entity_type === $entity_type && $approval->uda_section === $section && $approval->approval_type === $approval_type) {
                            $positions = $approval->approval;
                        }
                    }

                }

                add_post_meta($request_id, 'legal_entity', $_POST['entity']);
                add_post_meta($request_id, 'entity_type', $entity_type);
                add_post_meta($request_id, 'uda_section', $section);
                add_post_meta($request_id, 'approval_type', $approval_type);
                add_post_meta($request_id, 'cost_center', trim($_POST['cost-center']));
                add_post_meta($request_id, 'cost_center_description', trim($_POST['description']));
                
                foreach($positions as $position) {
                    add_post_meta($request_id, $position, json_encode(array('')));
                }

                if($section === 'Non-CapEx') {
                    $title = $approval_type === 'Operation Approval' ? 'Group CEO' : 'Group CFO';
                    update_post_meta($request_id, $title, json_encode($company_leaders->$title));
                }

            }

        }

        $success = "Cost Center has been added!";

    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

if(isset($_POST['change-request'])) {
    try {

        if(is_user_logged_in()) {    

            foreach($_POST['ID'] as $id) {

                $existing_request = get_posts(array(
                    'post_type'=>'auth_change_request',
                    'post_status'=>'private',
                    'meta_query'=>array(
                        array('key'=>'auth_card_id', 'value'=>$id),
                        array('key'=>'status', 'value'=>'Waiting for submission')
                    ),
                    'posts_per_page'=>-1
                    )          
                )[0];

                if($existing_request) {
                    $request_id = $existing_request->ID;
                } else {
                    $request_id = wp_insert_post(array(
                        'post_type'=>'auth_change_request',
                        'post_title'=>'Auth Changing Request',
                        'post_status'=>'private'
                    ));
                }

                update_post_meta($request_id, 'auth_card_id', $id);
                $approvers = is_array($_POST['approver'][$id]) ? $_POST['approver'][$id] : array('');
                update_post_meta($request_id, $_POST['approval'], json_encode($approvers));
                
                if($_POST['uda-section'] === 'Employee Expense') {

                    $concur_auth_level = json_decode(get_post_meta($request_id, 'concur_auth_level', true));
                    $concur_auth_level->$_POST['approval'] = array();
                    
                    foreach($_POST['approver'][$id] as $key=>$approver) {
                        array_push($concur_auth_level->$_POST['approval'], array($approver=>$_POST['concur'][$id][$key]));                      
                    }

                    update_post_meta($request_id, 'concur_auth_level', json_encode($concur_auth_level));
                
                } else {

                    $payment_auth_level = json_decode(get_post_meta($request_id, 'payment_auth_level', true));
                    $payment_auth_level->$_POST['approval'] = array();
                    $pr_auth_level = json_decode(get_post_meta($request_id, 'pr_auth_level', true));
                    $pr_auth_level->$_POST['approval'] = array();
                    
                    foreach($_POST['approver'][$id] as $key=>$approver) {

                        array_push($payment_auth_level->$_POST['approval'], array($approver=>$_POST['payment'][$id][$key])); 
                        
                        $categories = array_map(function($array) use($key) {
                            return $array[$key];
                        }, $_POST['pr'][$id]);

                        array_push($pr_auth_level->$_POST['approval'], array($approver=>$categories));
                    }

                    update_post_meta($request_id, 'payment_auth_level', json_encode($payment_auth_level));
                    update_post_meta($request_id, 'pr_auth_level', json_encode($pr_auth_level));
                
                }

                $approvals = is_array(json_decode(get_post_meta($request_id, 'approval', true))) ? json_decode(get_post_meta($request_id, 'approval', true)) : array();
                if(!in_array($_POST['approval'], $approvals)) {
                    array_push($approvals, $_POST['approval']);
                }

                update_post_meta($request_id, 'approval', json_encode($approvals));
                update_post_meta($request_id, 'legal_entity', $_POST['entity']); 
                update_post_meta($request_id, 'uda_section', $_POST['uda-section']); 
                update_post_meta($request_id, 'approval_type', $_POST['approval-type']);
                update_post_meta($request_id, 'cost_center', get_post_meta($id, 'cost_center', true) . ' [' . get_post_meta($id, 'cost_center_description', true) . ']');    
                update_post_meta($request_id, 'requestor_id', get_current_user_id());
                update_post_meta($request_id, 'requestor', wp_get_current_user()->display_name);
                update_post_meta($request_id, 'requestor_email', wp_get_current_user()->user_email);
                update_post_meta($request_id, 'status', 'Waiting for submission');
                update_post_meta($request_id, 'changed_date', date("Y-m-d"));  
            
            }

            $success = "Request has been submitted!";

        } else {
            $error = "Please log in.";
        }

    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

if(isset($_POST['cancel-changes'])) {
    try {

        if(empty($change_requests)) {
            throw new Exception("No changes!");
        }

        foreach($change_requests as $change_request) {
            update_post_meta($change_request->ID, 'status', 'Canceled');
        }

        $success = "Request has been canceled!";

    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

if(isset($_POST['confirm'])) {
    try {

        if(empty($change_requests)) {
            throw new Exception("No changes!");
        }

        is_email(trim($_POST['approver'])) && $approver_email = trim($_POST['approver']);
        preg_match('/\<(.*?)\>/', $_POST['approver'], $matches);
        $matches && $approver_email = $matches[1];
        unset($matches);

        if(empty($approver_email)) {
            throw new Exception("Not valid email format: " . $_POST['approver'] . ". Please use Name &lt;prefix@fcagroup.com&gt;");
        }

        is_email(trim($_POST['requestor'])) && $requestor_email = trim($_POST['requestor']);
        preg_match('/\<(.*?)\>/', $_POST['requestor'], $matches);
        $matches && $requestor_email = $matches[1];
        unset($matches);

        if(empty($requestor_email)) {
            throw new Exception("Not valid email format: " . $_POST['requestor'] . ". Please use Name &lt;prefix@fcagroup.com&gt;");
        }

        $request_no = get_option('auth_change_request_no', 0) + 1;
        $approval_hash = md5($request_no . $_POST['requestor'] . NONCE_SALT);

        $approval_url = site_url() . '/auth-card-approval/?key=' . $approval_hash;
        $result = apaconnect_mail($approver_email, 'uda_to_approver', array(
            'date'=>date("Y-m-d"),
            'request_no'=>$request_no,
            'requestor'=>$_POST['requestor'],
            'approver'=>$_POST['approver'],
            'entity'=>$_POST['entity'],
            'comments'=>$_POST['comments'],
            'approval_link'=>$approval_url
        ));

        if($result) {

            include_once ABSPATH . 'wp-admin/includes/media.php';
            include_once ABSPATH . 'wp-admin/includes/file.php';
            include_once ABSPATH . 'wp-admin/includes/image.php';

            $attachment_id = media_handle_upload('signature', 0);

            if(is_wp_error($attachment_id)) {
                throw new Exception("Failed to upload signature: " . $attachment_id->get_errer_message());
            }

            update_option('auth_change_request_no', $request_no);
            
            $request_id = wp_insert_post(array(
                'post_type'=>'auth_card_request',
                'post_title'=>'Auth Card Request No. ' . $request_no,
                'post_status'=>'private'
            ));

            $change_requests_id = array();
            foreach($change_requests as $change_request) {
                array_push($change_requests_id, $change_request->ID);
                update_post_meta($change_request->ID, 'status', 'Pending for approval');
            }
            update_post_meta($request_id, 'change_requests_id', json_encode($change_requests_id));
            update_post_meta($request_id, 'signature_id', $attachment_id);
            update_post_meta($request_id, 'approver', $_POST['approver']);
            update_post_meta($request_id, 'approver_email', $approver_email);
            update_post_meta($request_id, 'requestor', $_POST['requestor']);
            update_post_meta($request_id, 'requestor_email', $requestor_email);
            update_post_meta($request_id, 'entity', $_POST['entity']);
            update_post_meta($request_id, 'request_no', $request_no);
            update_post_meta($request_id, 'approval_hash', $approval_hash);
            update_post_meta($request_id, 'submitted_date', date('Y-m-d'));
            update_post_meta($request_id, 'status', 'Pending for approval');

            $success = "Request has been submitted!";
            
        } else {
            throw new Exception("Fail to send email to " . $_POST['requestor']);
        }
        
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

$auth_cards = get_posts(array(
    'post_type'=>'auth_card',
    'post_status'=>'private',
    'meta_query'=>array(
        array('key'=>'approval_type', 'value'=>$_POST['approval-type']),
        array('key'=>'uda_section', 'value'=>$_POST['uda-section']),
        array('key'=>'legal_entity', 'value'=>$_POST['entity']),
        array('key'=>'entity_type', 'value'=>$_POST['entity-type'])
    ),
    'posts_per_page'=>-1
    )
);

$change_requests = get_posts(array(
    'post_type'=>'auth_change_request',
    'post_status'=>'private',
    'meta_query'=>array(
        array('key'=>'requestor_id', 'value'=>get_current_user_id()),
        array('key'=>'status', 'value'=>'Waiting for submission')
    ),
    'posts_per_page'=>-1
    )          
);
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
    width: 1200px;
    margin-left: -600px;
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
            <div class="tabbale">
                <ul class="nav nav-tabs">
                    <li class="active">
                        <a href="#master" data-toggle="tab">Master Table</a>
                    </li>
                    <li>
                        <a href="#changes" data-toggle="tab">Change Request</a>
                    </li>
                </ul>
            </div>
            <div class="tab-content">
                <div class="tab-pane active" id="master">
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
                    <div class="pull-right">
                        <button type="button" onclick="addEntity()" class="btn btn-primary">Add Legal Entity</button>
                        <button type="button" onclick="addNew()" class="btn btn-primary">Add Cost Center</button>
                    </div>
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
                                <?php if($_POST['uda-section'] === 'Non-CapEx') { ?>
                                <th class="level">Group Level</th>
                                <?php } ?>
                                <th class="level" colspan="5">Reginonal Level</th>
                            </tr>
                            <?php foreach($auth_cards as $auth_card) { ?>
                            <tr>
                                <td><?=$auth_card->cost_center_description?></td>
                                <td><?=$auth_card->cost_center?></td>
                                <td class="no-border"></td>
                                <?php foreach($uda_approval as $approval) { ?>
                                    <?php if($approval->approval_type === $_POST['approval-type'] && $approval->uda_section === $_POST['uda-section'] && $approval->entity_type === $_POST['entity-type']) { ?>
                                        <?php foreach($approval->approval as $position) { ?>
                                <td><?php foreach(json_decode($auth_card->$position) as $approver) { echo $approver . "<br>"; } ?></td>
                                        <?php } ?>
                                    <?php } ?>
                                <?php } ?>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <button type="button" onclick="changeRequest()" class="btn btn-success pull-right">Change</button>                        
                    <?php } ?>
                </div>
                <div class="tab-pane" id="changes">
                    <?php if(count($change_requests) > 0) { ?>
                    <form method="post" class="pull-right">
                        <button type="button" class="btn btn-primary" onclick="requestorInfo()">Fill in Requestor Information</button>
                        <button type="submit" class="btn btn-danger" name="cancel-changes">Cancel</button>
                    </form>
                    <?php } ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Legal Entity</th>
                                <th>UDA Section</th>
                                <th>Approval Type</th>
                                <th>Cost Center</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($change_requests as $row) { ?>
                            <tr>
                                <td><?=$row->ID?></td>
                                <td><?=$row->legal_entity?></td>
                                <td><?=$row->uda_section?></td>
                                <td><?=$row->approval_type?></td>
                                <td><?=$row->cost_center?></td>
                            </tr>
                            <tr>
                                <td colspan="5">
                                    <?php foreach(json_decode($row->approval) as $approval) { ?>
                                    <dl>
                                        <dt><?=$approval?></dt>
                                        <dd>
                                            <?php if($row->concur_auth_level) { ?>
                                            <dl>
                                                <dt>Concur Authorization Level</dt>
                                                <dd>
                                                <?php 
                                                $test = json_decode($row->concur_auth_level)->$approval;
                                                foreach($test as $approver) {
                                                    $name = array_keys((array)$approver)[0];
                                                    echo $name . ' : ' . $approver->$name . '<br>';
                                                }
                                                ?>
                                                </dd>
                                            </dl>
                                            <?php } else { ?>
                                            <dl>
                                                <dt>PR Approval Authorization Level</dt>
                                                <dd>
                                                <?php
                                                foreach(json_decode($row->pr_auth_level)->$approval as $item) { 
                                                    $name = array_keys((array)$item)[0];
                                                    echo $name . ' : ' . '<br>';
                                                    foreach((array)$item->$name as $category=>$value) {
                                                        echo $category . ' -> ' . $value . '<br>';
                                                    }
                                                } 
                                                ?>
                                                </dd>
                                            </dl>
                                            <dl>
                                                <dt>Payment Request Signing Authorization Level</dt>
                                                <dd>
                                                <?php 
                                                $test = json_decode($row->payment_auth_level)->$approval;
                                                foreach($test as $approver) {
                                                    $name = array_keys((array)$approver)[0];
                                                    echo $name . ' : ' . $approver->$name . '<br>';
                                                }
                                                ?>
                                                </dd>
                                            </dl>
                                            <?php } ?>
                                        </dd>
                                    </dl>
                                    <?php } ?>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
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
                <button type="submit" name="add-entity" class="btn btn-success">Submit</button>
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
                            <option value="<?=$auth_card->cost_center?>"><?=$auth_card->cost_center?> [<?=$auth_card->cost_center_description?>]</option>
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
                <button type="submit" name="change-request" class="btn btn-success">Submit</button>
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
                <div class="control-group">
                    <label class="control-label">Legal Entity</label>
                    <div class="controls">
                        <select name="entity" required>
                        <?php foreach($entities as $entity_type) { ?>
                            <?php foreach($entity_type as $entity) { ?>
                            <option value="<?=$entity?>"><?=$entity?></option>
                            <?php } ?>
                        <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label">UDA Section</label>
                    <div class="controls">
                        <label><input type="checkbox" name="uda-section[]" value="CapEx"> CapEx</label>
                        <label><input type="checkbox" name="uda-section[]" value="Non-CapEx"> Non-CapEx</label>
                        <label><input type="checkbox" name="uda-section[]" value="Employee Expense"> Employee Expense</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn close-modal">Close</button>
                <button type="submit" name="add-new" class="btn btn-success">Submit</button>
            </div>
        </form>
    </div>
    <div class="requestor-info-modal modal hide fade">
        <form method="post" class="form-horizontal" enctype="multipart/form-data">
            <div class="modal-header">
                <h3 class="title">Please fill in your information</h3>
            </div>
            <div class="modal-body">
                <div class="control-group">
                    <label class="control-label">Requestor</label>
                    <div class="controls">
                        <input type="text" class="user-name" name="requestor" autocomplete="off" placeholder="Name <prefix@fcagroup.com>" required />
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label">Legal Entity</label>
                    <div class="controls">
                        <select name="entity" required>
                        <?php foreach($entities as $entity_type) { ?>
                            <?php foreach($entity_type as $entity) { ?>
                            <option value="<?=$entity?>"><?=$entity?></option>
                            <?php } ?>
                        <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label">Approver</label>
                    <div class="controls">
                        <input type="text" class="user-name" name="approver" autocomplete="off" placeholder="Name <prefix@fcagroup.com>" required />
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label">Comments</label>
                    <div class="controls">
                        <textarea name="comments"></textarea>
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label">Signature Upload</label>
                    <div class="controls">
                        <input type="file" name="signature" required />
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn close-modal">Close</button>
                <button type="submit" name="confirm" class="btn btn-success">Submit</button>
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

    window.requestorInfo = function() {
        $('.requestor-info-modal').modal('show');
    }

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

    $('.btn-success').click(function() {
        btn_submit = $(this);
        setTimeout(function() {
            btn_submit.button('reset');
        }, 5000);
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
        highLighter: function(item) {
            return item;
        } 
    });

});
</script>