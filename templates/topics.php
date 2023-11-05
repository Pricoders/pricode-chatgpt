<h1> Topics </h1>
<form id="add-new-topic-form">
<input type="text" placeholder="Add a new topic" name="new_topic" id="pricode-new-topic"/>
<?php wp_nonce_field('pricode_add_topic', 'pricode_add_topic_nonce') ?>
<button class="add-topic">Add topic</button>
</form>
<small class="response"></small>
<table>
	<thead>
		<tr>
			<td>
				Name
			</td>	
			<td>
				Last published
			</td>	
			
		</tr>
	</thead>
	<tbody>

		<?php 
		foreach ( $topics as $topic ) {
			echo '<tr>';
			echo "<td>{$topic->topic}</td>	
			<td>{$topic->last_published}</td>";
			echo '</tr>';
		}
		?>
		
			
		</tr>
	</tbody>
</table>


<script>
	jQuery('.add-topic').click(function(e){
		e.preventDefault();
		data = {
                action: 'pricode_add_topic',
                data: jQuery('#add-new-topic-form').serialize()
            }
		jQuery.post('<?php echo admin_url( 'admin-ajax.php' ) ?>', data, function(response){
			if(response.success){
				jQuery('small.response').html(response.message);
				setTimeout(function(){ window.location.reload() }, 1200);
			}
		}, 'json');
	})

</script>