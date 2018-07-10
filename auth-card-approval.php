<?php
$ICT_team = json_decode(get_option("UDA_ICT_team"));

$WCF_email = get_email_address($ICT_team->WCF);
$compliance_email = get_email_address($ICT_team->compliance);
$concur_email = get_email_address($ICT_team->Concur);
$SAP_email = get_email_address($ICT_team->SAP);

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

if(wp_get_current_user()->user_email === $SAP_email) {
    $change_requests = get_posts(array(
        'post_type'=>'auth_change_request',
        'post_status'=>'private',
        'post__in'=>$change_requests_id,
        'meta_query'=>array(
            'relation' => 'OR',
            array('key'=>'uda_section', 'value'=>'CapEx'),
            array('key'=>'uda_section', 'value'=>'Non-CapEx')
        ),
        'posts_per_page'=>-1
        )          
    );
} elseif(wp_get_current_user()->user_email === $concur_email) {
    $change_requests = get_posts(array(
        'post_type'=>'auth_change_request',
        'post_status'=>'private',
        'post__in'=>$change_requests_id,
        'meta_query'=>array(
            array('key'=>'uda_section', 'value'=>'Employee Expense')
        ),
        'posts_per_page'=>-1
        )          
    );
} else {
    $change_requests = get_posts(array(
        'post_type'=>'auth_change_request',
        'post_status'=>'private',
        'post__in'=>$change_requests_id,
        'posts_per_page'=>-1
        )          
    );
}

if(empty($auth_card_request)) {
    $error = 'Invalid approve key. The request has probably been removed.';
}

if(isset($_POST['status'])) {
    try {

        if($auth_card_request->status === 'Pending for approval') {

            $status = $_POST['status'] === 'reject' ? 'Rejected by Approver' : 'Pending for Compliance verification';

            if($_POST['status'] === 'approve') {

                $approval_hash = md5($auth_card_request->request_no . $ICT_team->compliance . NONCE_SALT);
                $approval_url = site_url() . '/auth-card-approval/?key=' . $approval_hash;

                $result_to_compliance = apaconnect_mail($compliance_email, 'uda_to_compliance', array(
                    'request_no'=>$auth_card_request->request_no,
                    'requestor'=>$auth_card_request->requestor,
                    'approver'=>$auth_card_request->approver,
                    'entity'=>$auth_card_request->entity,
                    'comments'=>$_POST['comments'],
                    'status'=>$status,
                    'approval_link'=>$approval_url,
                    'date'=>$auth_card_request->submitted_date
                ));
            
                if($result_to_compliance) {
                    update_post_meta($auth_card_request->ID, 'approval_hash', $approval_hash);
                } else {
                    throw new Exception("Fail to send email to " . $ICT_team->compliance);
                }

            } else {
                delete_post_meta($auth_card_request->ID, 'approval_hash');
            }
            
            $result_to_requestor = apaconnect_mail($auth_card_request->requestor_email, 'uda_to_requestor', array(
                'request_no'=>$auth_card_request->request_no,
                'requestor'=>$auth_card_request->requestor,
                'approver'=>$auth_card_request->approver,
                'entity'=>$auth_card_request->entity,
                'comments'=>$_POST['comments'],
                'status'=>$status,
                'date'=>$auth_card_request->submitted_date
            ), false);

            if($result_to_requestor) {
                update_post_meta($auth_card_request->ID, 'status', $status);
                $success = "Request has been " . $status;
            } else {
                throw new Exception("Fail to send email to " . $auth_card_request->requestor);
            }
        
        } elseif($auth_card_request->status === 'Pending for Compliance verification') {

            $status = $_POST['status'] === 'reject' ? 'Rejected by Compliance' : 'Pending for system change';

            if($_POST['status'] === 'approve') {

                $approval_hash = md5($auth_card_request->request_no . $ICT_team->Concur . $ICT_team->SAP . NONCE_SALT);
                $approval_url = site_url() . '/auth-card-approval/?key=' . $approval_hash;

                foreach($change_requests as $change_request) {
                    if($change_request->concur_auth_level) {
                        $concur_change = true;
                    }
                    if($change_request->pr_auth_level) {
                        $sap_change = true;
                    }
                }

                if($concur_change) {

                    $result_to_concur = apaconnect_mail($concur_email, 'uda_to_ict', array(
                        'request_no'=>$auth_card_request->request_no,
                        'requestor'=>$auth_card_request->requestor,
                        'approver'=>$auth_card_request->approver,
                        'entity'=>$auth_card_request->entity,
                        'status'=>$status,
                        'approval_link'=>$approval_url,
                        'date'=>$auth_card_request->submitted_date
                    ), false);

                    if(!$result_to_concur) {
                        throw new Exception("Fail to send email to " . $ICT_team->Concur);
                    }
                } else {
                    update_post_meta($auth_card_request->ID, 'job_status_Concur', 'No system change required');
                }

                if($sap_change) {

                    $result_to_sap = apaconnect_mail($SAP_email, 'uda_to_ict', array(
                        'request_no'=>$auth_card_request->request_no,
                        'requestor'=>$auth_card_request->requestor,
                        'approver'=>$auth_card_request->approver,
                        'entity'=>$auth_card_request->entity,
                        'status'=>$status,
                        'approval_link'=>$approval_url,
                        'date'=>$auth_card_request->submitted_date
                    ), false);

                    if(!$result_to_sap) {
                        throw new Exception("Fail to send email to " . $ICT_team->SAP);
                    }
                } else {
                    update_post_meta($auth_card_request->ID, 'job_status_SAP', 'No system change required');
                }

                update_post_meta($auth_card_request->ID, 'approval_hash', $approval_hash);

            } else {
                delete_post_meta($auth_card_request->ID, 'approval_hash');
            }

            $result_to_requestor = apaconnect_mail($auth_card_request->requestor_email, 'uda_to_requestor', array(
                'request_no'=>$auth_card_request->request_no,
                'requestor'=>$auth_card_request->requestor,
                'approver'=>$auth_card_request->approver,
                'entity'=>$auth_card_request->entity,
                'comments'=>$_POST['comments'],
                'status'=>$status,
                'date'=>$auth_card_request->submitted_date
            ), false);

            update_post_meta($auth_card_request->ID, 'status', $status);
            $success = "Request has been " . $status;

        }
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

if(isset($_POST['proceed'])) {
    try {

        if(wp_get_current_user()->user_email === $SAP_email) {
            $type = 'SAP';
        } elseif(wp_get_current_user()->user_email === $concur_email) {
            $type = 'Concur';
        } else {
            throw new Exception("Please login as SAP or Concur responsibility!");
        }

        update_post_meta($auth_card_request->ID, 'job_status_' . $type, $_POST['job-status']);
        
        if($_POST['job-status'] === 'System change completed') {

            include_once ABSPATH . 'wp-admin/includes/media.php';
            include_once ABSPATH . 'wp-admin/includes/file.php';
            include_once ABSPATH . 'wp-admin/includes/image.php';

            $attachment_id = media_handle_upload('attachment', 0);

            if(is_wp_error($attachment_id)) {
                throw new Exception("Failed to upload signature: " . $attachment_id->get_errer_message());
            }

            update_post_meta($auth_card_request->ID, $type . '_attachment_id', $attachment_id);

        }

        $approval_hash = get_post_meta($auth_card_request->ID, 'approval_hash', true);
        $approval_url = site_url() . '/auth-card-approval/?key=' . $approval_hash;

        $result_to_wcf = apaconnect_mail($WCF_email, 'uda_to_ict', array(
            'request_no'=>$auth_card_request->request_no,
            'requestor'=>$auth_card_request->requestor,
            'approver'=>$auth_card_request->approver,
            'entity'=>$auth_card_request->entity,
            'status'=>$status,
            'approval_link'=>$approval_url,
            'date'=>$auth_card_request->submitted_date
        ), false);

        if(!$result_to_wcf) {
            throw new Exception("Fail to send email to " . $ICT_team->WCF);
        } else {
            $success = "Status changed, email was sent to " . $ICT_team->WCF;
        }

    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

if(isset($_POST['finish'])) {
    try {

        /* update master table */
        foreach($change_requests as $change_request) {
            foreach(json_decode($change_request->approval) as $position) {
                update_post_meta($change_request->auth_card_id, $position, $change_request->$position);
                foreach(json_decode($change_request->$position) as $approver) {
                    if($change_request->payment_auth_level) {
                        foreach(json_decode($change_request->payment_auth_level)->$position as $item) {
                            if(key((array) $item) === $approver) {
                                update_post_meta($change_request->auth_card_id, 'payment_auth_level-' . $approver, $item->$approver);
                            }
                        }
                    }
                    if($change_request->pr_auth_level) {
                        foreach(json_decode($change_request->pr_auth_level)->$position as $item) {
                            if(key((array) $item) === $approver) {
                                update_post_meta($change_request->auth_card_id, 'pr_auth_level-' . $approver, json_encode($item->$approver));
                            }
                        }
                    }
                    if($change_request->concur_auth_level) {
                        foreach(json_decode($change_request->concur_auth_level)->$position as $item) {
                            if(key((array) $item) === $approver) {
                                update_post_meta($change_request->auth_card_id, 'concur_auth_level-' . $approver, $item->$approver);
                            }
                        }
                    }
                }
            }
        }

        $status = 'Finished';
        update_post_meta($auth_card_request->ID, 'status', $status);
        delete_post_meta($auth_card_request->ID, 'approval_hash');

        apaconnect_mail($auth_card_request->requestor_email, 'uda_to_requestor', array(
            'request_no'=>$auth_card_request->request_no,
            'requestor'=>$auth_card_request->requestor,
            'approver'=>$auth_card_request->approver,
            'entity'=>$auth_card_request->entity,
            'comments'=>$_POST['comments'],
            'status'=>$status,
            'date'=>$auth_card_request->submitted_date
        ), false);

        $success = "Request has been finished.";

    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<style>
.control-group {
    display: inline-block;
    margin-right: 50px;
}
</style>

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
            <button type="button" onclick="proceedApproval()" class="btn btn-primary pull-right">Proceed Approval</button>
            <div class="form-horizontal">
                <div class="control-group">
                    <label class="control-label">Requestor Signature</label>
                    <div class="controls">
                        <a href="<?=wp_get_attachment_url(get_post_meta($auth_card_request->ID, 'signature_id', true))?>" target="_blank"><?=get_the_title(get_post_meta($auth_card_request->ID, 'signature_id', true))?></a>
                    </div>
                </div>
                <?php if(get_post_meta($auth_card_request->ID, 'SAP_attachment_id', true)) { ?>
                <div class="control-group">
                    <label class="control-label">SAP Attachment</label>
                    <div class="controls">
                        <a href="<?=wp_get_attachment_url(get_post_meta($auth_card_request->ID, 'SAP_attachment_id', true))?>" target="_blank"><?=get_the_title(get_post_meta($auth_card_request->ID, 'SAP_attachment_id', true))?></a>
                    </div>
                </div>
                <?php } if(get_post_meta($auth_card_request->ID, 'Concur_attachment_id', true)) { ?>
                <div class="control-group">
                    <label class="control-label">Concur Attachment</label>
                    <div class="controls">
                        <a href="<?=wp_get_attachment_url(get_post_meta($auth_card_request->ID, 'Concur_attachment_id', true))?>" target="_blank"><?=get_the_title(get_post_meta($auth_card_request->ID, 'Concur_attachment_id', true))?></a>
                    </div>
                </div>
                <?php } ?>
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
        <form method="post" class="form-horizontal" style="margin-bottom:0" enctype="multipart/form-data">
            <div class="modal-header">
                <h3 class="title">Proceed Approval</h3>
            </div>
            <div class="modal-body">
                <?php if(wp_get_current_user()->user_email != $WCF_email) { ?>
                    <?php if($auth_card_request->status === 'Pending for system change') { ?>
                <div class="control-group">
                    <label class="control-label">Job Status</label>
                    <div class="controls">
                        <select name="job-status" id="job-status">
                            <option value="System change completed">System change completed</option>
                            <option value="No system change required">No system change required</option>
                            <option value="Pending for more information from business">Pending for more information from business</option>
                        </select>
                    </div>
                </div>
                <div class="control-group" id="attachment">
                    <label class="control-label">Attachment</label>
                    <div class="controls">
                        <input type="file" name="attachment" required />
                    </div>
                </div>
                    <?php } ?>
                <?php } ?>
                <div class="control-group">
                    <label class="control-label">Comments</label>
                    <div class="controls">
                        <textarea name="comments"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn close-modal">Close</button>
                <?php if($auth_card_request->status === 'Pending for system change') { ?>
                    <?php if(wp_get_current_user()->user_email == $WCF_email) { ?>
                <button type="submit" name="finish" class="btn btn-success">Finish</button>
                    <?php } else { ?>
                <button type="submit" name="proceed" class="btn btn-success">Proceed</button>
                    <?php } ?>
                <?php } else { ?>
                <button type="submit" name="status" value="reject" class="btn btn-danger">Reject</button>
                <button type="submit" name="status" value="approve" class="btn btn-success">Approve</button>
                <?php } ?>
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

    $('#job-status').change(function() {
        if($(this).val() !== 'System change completed') {
            $('#attachment').hide();
            $('#attachment input').attr('required', false);
        } else {
            $('#attachment').show();
            $('#attachment input').attr('required', true);
        }
    });

    $('.btn-success').click(function() {
        btn_submit = $(this);
        setTimeout(function() {
            btn_submit.button('reset');
        }, 5000);
    });
    
});
</script>