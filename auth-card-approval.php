<?php
$ICT_team = json_decode(get_option("UDA_ICT_team"));

$WCF_emails = array_map('get_email_address', $ICT_team->WCF);
$compliance_emails = array_map('get_email_address', $ICT_team->Compliance);
$concur_emails = array_map('get_email_address', $ICT_team->Concur);
$SAP_emails = array_map('get_email_address', $ICT_team->SAP);
$account_emails = array_map('get_email_address', $ICT_team->Account);

$auth_card_request = get_posts(array(
    'post_type'=>'auth_card_request',
    'post_status'=>'private',
    'meta_query'=>array(
        array('key'=>'request_no', 'value'=>$_GET['request'])
    ),
    'posts_per_page'=>-1
    )          
)[0];

$approval_url = site_url() . '/auth-card-approval/?request=' . $auth_card_request->request_no;

$change_requests_id = json_decode($auth_card_request->change_requests_id);

if(in_array(wp_get_current_user()->user_email, $SAP_emails)) {
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
} elseif(in_array(wp_get_current_user()->user_email ,$concur_emails)) {
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

                foreach($compliance_emails as $email) {
                    $result_to_compliance = apaconnect_mail($email, 'uda_to_compliance', array(
                        'request_no'=>$auth_card_request->request_no,
                        'requestor'=>$auth_card_request->requestor,
                        'approver'=>$auth_card_request->approver,
                        'entity'=>$auth_card_request->entity,
                        'comments'=>$_POST['comments'],
                        'status'=>$status,
                        'approval_link'=>$approval_url,
                        'date'=>$auth_card_request->submitted_date
                    ));

                    if(!$result_to_compliance) {
                        throw new Exception("Fail to send email to " . $email);
                    }
                }

                update_post_meta($auth_card_request->ID, 'approve_date', date('Y-m-d'));

            }

            apaconnect_mail($auth_card_request->requestor_email, 'uda_to_requestor', array(
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
        
        } elseif($auth_card_request->status === 'Pending for Compliance verification') {

            $status = $_POST['status'] === 'reject' ? 'Rejected by Compliance' : 'Pending for system change';

            if($_POST['status'] === 'approve') {

                // $approval_hash = md5($auth_card_request->request_no . $ICT_team->Concur . $ICT_team->SAP . NONCE_SALT);
                // $approval_url = site_url() . '/auth-card-approval/?request-no=' . $auth_card_request->request_no;

                foreach($change_requests as $change_request) {
                    if($change_request->concur_auth_level) {
                        $concur_change = true;
                    }
                    if($change_request->pr_auth_level) {
                        $sap_change = true;
                    }
                }

                if($concur_change) {

                    foreach($concur_emails as $email) {

                        $result_to_concur = apaconnect_mail($email, 'uda_to_ict', array(
                            'request_no'=>$auth_card_request->request_no,
                            'requestor'=>$auth_card_request->requestor,
                            'approver'=>$auth_card_request->approver,
                            'entity'=>$auth_card_request->entity,
                            'status'=>$status,
                            'approval_link'=>$approval_url,
                            'date'=>$auth_card_request->submitted_date
                        ), false);

                        if(!$result_to_concur) {
                            throw new Exception("Fail to send email to " . $email);
                        }

                    }
                    
                } else {
                    update_post_meta($auth_card_request->ID, 'job_status_Concur', 'No system change required');
                }

                if($sap_change) {

                    foreach($SAP_emails as $email) {

                        $result_to_sap = apaconnect_mail($email, 'uda_to_ict', array(
                            'request_no'=>$auth_card_request->request_no,
                            'requestor'=>$auth_card_request->requestor,
                            'approver'=>$auth_card_request->approver,
                            'entity'=>$auth_card_request->entity,
                            'status'=>$status,
                            'approval_link'=>$approval_url,
                            'date'=>$auth_card_request->submitted_date
                        ), false);

                        if(!$result_to_sap) {
                            throw new Exception("Fail to send email to " . $email);
                        }

                    }

                    foreach($account_emails as $email) {

                        apaconnect_mail($email, 'uda_to_ict', array(
                            'request_no'=>$auth_card_request->request_no,
                            'requestor'=>$auth_card_request->requestor,
                            'approver'=>$auth_card_request->approver,
                            'entity'=>$auth_card_request->entity,
                            'status'=>$status,
                            'approval_link'=>$approval_url,
                            'date'=>$auth_card_request->submitted_date
                        ), false);

                    }

                } else {
                    update_post_meta($auth_card_request->ID, 'job_status_SAP', 'No system change required');
                }

                update_post_meta($auth_card_request->ID, 'verify_name', wp_get_current_user()->display_name);
                update_post_meta($auth_card_request->ID, 'verify_date', date('Y-m-d'));
            
            }

            apaconnect_mail($auth_card_request->requestor_email, 'uda_to_requestor', array(
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

        if(in_array(wp_get_current_user()->user_email, $SAP_emails)) {
            $type = 'SAP';
        } elseif(in_array(wp_get_current_user()->user_email, $concur_emails)) {
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
            update_post_meta($auth_card_request->ID, $type . '_complete_date', date('Y-m-d'));

        }

        foreach($WCF_emails as $email) {

            $result_to_wcf = apaconnect_mail($email, 'uda_to_ict', array(
                'request_no'=>$auth_card_request->request_no,
                'requestor'=>$auth_card_request->requestor,
                'approver'=>$auth_card_request->approver,
                'entity'=>$auth_card_request->entity,
                'status'=>$status,
                'approval_link'=>$approval_url,
                'date'=>$auth_card_request->submitted_date
            ), false);

            if(!$result_to_wcf) {
                throw new Exception("Fail to send email to " . $email);
            }

        }

        $success = "Status changed, email was sent out.";

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
        update_post_meta($auth_card_request->ID, 'finish_date', date('Y-m-d'));

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
.controls > a {
    color: #BC3656;
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
            <?php if(get_post_meta($auth_card_request->ID, 'status', true) === 'Pending for Compliance verification' && in_array(wp_get_current_user()->user_email, $compliance_emails)) { ?>
            <button type="button" onclick="proceedApproval()" class="btn btn-primary pull-right">Proceed Compliance verification</button>
            <?php } elseif(get_post_meta($auth_card_request->ID, 'status', true) === 'Pending for system change' && (in_array(wp_get_current_user()->user_email, $SAP_emails) || in_array(wp_get_current_user()->user_email, $concur_emails) || in_array(wp_get_current_user()->user_email, $WCF_emails))) { ?>
            <button type="button" onclick="proceedApproval()" class="btn btn-primary pull-right">Proceed System Change</button>
            <?php } elseif(get_post_meta($auth_card_request->ID, 'status', true) === 'Pending for approval') { ?>
            <button type="button" onclick="proceedApproval()" class="btn btn-primary pull-right">Proceed Approval</button>
            <?php } ?>
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
                <?php if(!in_array(wp_get_current_user()->user_email, $WCF_emails)) { ?>
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
                    <?php if(in_array(wp_get_current_user()->user_email, $WCF_emails)) { ?>
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