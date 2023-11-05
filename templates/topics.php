<h1> Topics </h1>

<input type="text" placeholder="Add a new topic" /><button class="add-topic">Add topic</button>
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
