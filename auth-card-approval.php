<?php
$change_requests = get_posts(array(
    'post_type'=>'auth_change_request',
    'post_status'=>'private',
    'meta_query'=>array(
        array('key'=>'approval_hash', 'value'=>$_GET['key'])
    ),
    'posts_per_page'=>-1
    )          
);
?>

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