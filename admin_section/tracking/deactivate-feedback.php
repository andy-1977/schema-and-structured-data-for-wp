<?php 
$reasons = array(
    	1 => '<li><label><input type="radio" name="saswp_disable_reason" value="temporary"/>' . __('It is only temporary', 'schema-and-structured-data-for-wp') . '</label></li>',
		2 => '<li><label><input type="radio" name="saswp_disable_reason" value="stopped"/>' . __('I stopped using Schema plugin on my site', 'schema-and-structured-data-for-wp') . '</label></li>',
		3 => '<li><label><input type="radio" name="saswp_disable_reason" value="missing"/>' . __('I miss a feature', 'schema-and-structured-data-for-wp') . '</label></li>
		<li><input class="mb-box missing" type="text" name="saswp_disable_text[]" value="" placeholder="Please describe the feature"/></li>',
		4 => '<li><label><input type="radio" name="saswp_disable_reason" value="technical"/>' . __('Technical Issue', 'schema-and-structured-data-for-wp') . '</label></li>
		<li><textarea class="mb-box technical" name="saswp_disable_text[]" placeholder="' . __('How Can we help? Please describe your problem', 'schema-and-structured-data-for-wp') . '"></textarea></li>',
		5 => '<li><label><input type="radio" name="saswp_disable_reason" value="another plugin"/>' . __('I switched to another plugin', 'schema-and-structured-data-for-wp') .  '</label></li>
		<li><input class="mb-box another" type="text" name="saswp_disable_text[]" value="" placeholder="Name of the plugin"/></li>',
		6 => '<li><label><input type="radio" name="saswp_disable_reason" value="other"/>' . __('Other reason', 'schema-and-structured-data-for-wp') . '</label></li>
		<li><textarea class="mb-box other" name="saswp_disable_text[]" placeholder="' . __('Please specify, if possible', 'schema-and-structured-data-for-wp') . '"></textarea></li>',
    );

shuffle( $reasons );

?>


<div id="saswp-reloaded-feedback-overlay" style="display: none;">
    <div id="saswp-reloaded-feedback-content">
	<form action="" method="post">
	    <h3><strong><?php echo esc_html__( 'If you have a moment, please let us know why you are deactivating:', 'schema-and-structured-data-for-wp' ); ?></strong></h3>
	    <ul>
                <?php 
                foreach ( $reasons as $reason_escaped ){
					//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped	-- all html inside this variable already escaped above in $reasons variable
                    echo $reason_escaped;
                }
                ?>
	    </ul>
	    <?php if ( $email ) : ?>
    	    <input type="hidden" name="saswp_disable_from" value="<?php echo esc_attr( $email ); ?>"/>
	    <?php endif; ?>
	    <input id="saswp-reloaded-feedback-submit" class="button button-primary" type="submit" name="saswp_disable_submit" value="<?php esc_attr_e('Submit & Deactivate', 'schema-and-structured-data-for-wp' ); ?>"/>
	    <a class="button"><?php echo esc_html__( 'Only Deactivate', 'schema-and-structured-data-for-wp' ); ?></a>
	    <a class="saswp-for-wp-feedback-not-deactivate" href="#"><?php echo esc_html__( 'Don\'t deactivate', 'schema-and-structured-data-for-wp' ); ?></a>
	    <?php wp_nonce_field( 'saswp_feedback_nonce', 'saswp_feedback_nonce' );   ?>
	</form>
    </div>
</div>