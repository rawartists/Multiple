<script type="text/javascript">

	$(document).ready(function() {

		var selectize = $('#<?php echo $form_slug; ?>');

		selectize.selectize({
			maxItems: <?php echo $max_selections; ?>,
			valueField: '<?php echo $value_field; ?>',
			labelField: '<?php echo $label_field; ?>',
			searchField: '<?php echo $search_field; ?>',
			options: [],
			create: false,
			render: {
				/*item: function(item, escape) {
					return '<div>' +
						(item.name ? '<span class="name">' + escape(item.name) + '</span>' : '') +
						(item.email ? '<span class="email">' + escape(item.email) + '</span>' : '') +
					'</div>';
				},*/
				option: function(item, escape) {
					return '<div>' + item.<?php echo $label_field; ?> + '</div>';
				}
			},
			load: function(query, callback) {
				if (!query.length) return callback();
				$.ajax({
					url: SITE_URL + 'streams_core/public_ajax/field/multiple/search/<?php echo $stream; ?>/<?php echo $field_slug; ?>?query=' + encodeURIComponent(query),
					type: 'GET',
					error: function() {
						callback();
					},
					success: function(results) {
						callback(results.entries);
					}
				});
			}
		});
	});
</script>