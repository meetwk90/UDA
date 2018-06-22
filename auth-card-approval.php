<?php
$auth_card_request = get_posts(array(
    'post_type'=>'auth_card_request',
    'post_status'=>'private',
    'meta_query'=>array(
        array('key'=>'approval_hash', 'value'=>$_GET['key'])
    ),
    'posts_per_page'=>-1
    )          
)[0];

$change_requests_id = json_decode($auth_card_request->change_requests_id);
$change_requests = get_posts(array(
    'post_type'=>'auth_change_request',
    'post_status'=>'private',
    'post__in'=>$change_requests_id,
    'posts_per_page'=>-1
    )          
);

if(empty($auth_card_request)) {
    $error = 'Invalid approve key. The request has probably been removed.';
}

if(isset($_POST['status'])) {
    try {
        $status = $_POST['status'] === 'reject' ? 'Rejected by Approver' : 'Pending for Compliance verification';
        update_post_meta($auth_card_request->ID, 'status', $status);

        $result = apaconnect_mail($auth_card_request->requestor_email, 'uda_to_requestor', array(
            'request_no'=>$auth_card_request->request_no,
            'requestor'=>$auth_card_request->requestor,
            'approver'=>$auth_card_request->approver,
            'entity'=>$auth_card_request->entity,
            'comments'=>$_POST['comments'],
            'status'=>$status,
            'date'=>$auth_card_request->submitted_date
        ), false);

        if($result) {
            delete_post_meta($auth_card_request->ID, 'approval_hash');
            $success = "Request has been " . $status;
        } else {
            throw new Exception("Fail to send email to " . $auth_card_request->requestor);
        }
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="site-content box" role="main">
	<header>
        Authority Card Request Approval (Request No. <?php echo $auth_card_request->request_no; ?>)
    </header>
	<div class="content">
        <?php if($error) { ?>
        <div class="alert alert-error"><?=$error?></div>
        <?php } ?>
        <?php if($success) { ?>
        <div class="alert alert-success"><?=$success?></div>
        <?php } ?>
        <?php if(!$success && !$error) { ?>
		<div class="row-fluid">
            <div class="pull-right">
                <button type="button" onclick="proceedApproval()" class="btn btn-primary">Proceed Approval</button>
            </div>
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
        <?php } ?>
    </div>
</div>

<div class="modal-container">
    <div class="proceed-approval-modal modal hide fade">
        <form method="post" class="form-horizontal" style="margin-bottom:0">
            <div class="modal-header">
                <h3 class="title">Proceed Approval</h3>
            </div>
            <div class="modal-body">
                <div class="control-group">
                    <label class="control-label">Comments</label>
                    <div class="controls">
                        <textarea name="comments"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn close-modal">Close</button>
                <button type="submit" name="status" value="reject" class="btn btn-danger">Reject</button>
                <button type="submit" name="status" value="approve" class="btn btn-success">Approve</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(function($) {
    $('.modal-container').on('click', 'button.close-modal', function() {
		$(this).closest('.modal').modal('hide');
	});
    window.proceedApproval = function() {
        $('.proceed-approval-modal').modal('show');
    }
});
</script>