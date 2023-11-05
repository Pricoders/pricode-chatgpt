<h1> Logs </h1>
<table>
	<thead>
		<tr>
			<td>
				Event name
			</td>	
			<td>
				Type
			</td>	
			<td>
				Description
			</td>	
			<td>
				Created
			</td>	
		</tr>
	</thead>
	<tbody>

		<?php 
		foreach ( $logs as $log ) {
			echo '<tr>';
			echo "<td>
				{$log->event_name}
			</td>	
			<td>
				{$log->event_type}
			</td>	
			<td>
				{$log->description}
			</td>	
			<td>
				{$log->created_at}
			</td>";
			echo '</tr>';
		}
		?>
		
			
		</tr>
	</tbody>
</table>
