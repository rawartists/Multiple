<?php namespace Pyro\FieldType;

use Pyro\Module\Streams_core\Cp;
use Pyro\Module\Streams_core\Data;
use Pyro\Module\Streams_core\Core\Model;
use Pyro\Module\Streams_core\Core\Field\AbstractField;

/**
 * Streams Entry Selector Field Type
 *
 * @package		PyroCMS\Addons\Shared Addons\Field Types
 */
class Multiple extends AbstractField
{
	public $field_type_slug = 'multiple';

	public $db_col_type = 'integer';

	public $version = '1.0';

	public $alt_process = true;
	
	/**
	 * Field options
	 * @var array
	 */
	public $custom_parameters = array(
		'stream',
		'max_selections',
		'placeholder',
		'value_field',
		'label_field',
		'search_field',
		'template',
		'module_slug',
		'relation_class'
	);

	/**
	 * About meh
	 * @var array
	 */
	public $author = array(
		'name' => 'AI Web Systems, Inc. - Ryan Thompson',
		'url' => 'http://www.aiwebsystems.com/'
		);

	/**
	 * Relation
	 * @return object The relation object
	 */
	public function relation()
	{
		return $this->belongsToManyEntries($this->getParameter('relation_class', 'Pyro\Module\Streams_core\Core\Model\Entry'));
	}

	/**
	 * Output form input
	 *
	 * @access 	public
	 * @return	string
	 */
	public function formInput()
	{
		// Start the HTML
		$html = form_dropdown($this->form_slug.'_selections[]', array(), null, 'id="'.$this->form_slug.'" class="skip" placeholder="'.lang_label($this->getParameter('placeholder', 'lang:streams:multiple.placeholder')).'"');

		// Append our JS to the HTML since it's special
		$html .= $this->view(
			'fragments/multiple.js.php',
			array(
				'form_slug' => $this->form_slug,
				'field_slug' => $this->field->field_slug,
				'stream_namespace' => $this->stream->stream_namespace,
				'stream_param' => $this->getParameter('stream'),
				'max_selections' => $this->getParameter('max_selections', 'null'),
				'value_field' => $this->getParameter('value_field', 'id'),
				'label_field' => $this->getParameter('label_field', 'id'),
				'search_field' => $this->getParameter('search_field', 'id'),
				),
			false
			);

		return $html;
	}

	/**
	 * Output filter input
	 *
	 * @access 	public
	 * @return	string
	 */
	public function filterInput()
	{
		// Start the HTML
		$html = form_dropdown($this->getFilterSlug('contains'), array(), null, 'id="'.$this->getFilterSlug('contains').'" class="skip" placeholder="'.$this->field->field_name.'"');

		// Append our JS to the HTML since it's special
		$html .= $this->view(
			'fragments/multiple.js.php',
			array(
				'form_slug' => $this->getFilterSlug('contains'),
				'field_slug' => $this->field->field_slug,
				'stream_namespace' => $this->stream->stream_namespace,
				'stream_param' => $this->getParameter('stream'),
				'max_selections' => 1,
				'value_field' => $this->getParameter('value_field', 'id'),
				'label_field' => $this->getParameter('label_field', 'id'),
				'search_field' => $this->getParameter('search_field', 'id'),
				),
			false
			);

		return $html;
	}

	/**
	 * Process before saving
	 * @return string
	 */
	public function preSave()
	{
		// Setup
		$this->setTable();

		// Delete existing
		ci()->pdb->table($this->table)->where('entry_id', $this->entry->getKey())->delete();

		// Process / insert
		$insert = array();

		foreach ((array) $this->getSelectionsValue() as $id) {

			// Gotta have an ID
			if (empty($id) or $id < 1) continue;

			// Add to our insert
			$insert[] = array(
				'entry_id' => $this->entry->getKey(),
				'related_id' => $id
			);
		}

		// Insert new records
		if (! empty($insert)) 
		{
			ci()->pdb->table($this->table)->where('entry_id', $this->entry->getKey())->insert($insert);
		}

		// Return the count
		return (string) count($insert);
	}

	/**
	 * Pre Ouput
	 *
	 * Process before outputting on the CP. Since
	 * there is less need for performance on the back end,
	 * this is accomplished via just grabbing the title column
	 * and the id and displaying a link (ie, no joins here).
	 *
	 * @return	mixed 	null or string
	 */
	public function stringOutput()
	{
		if($entries = $this->getRelation() and ! $entries->isEmpty())
		{
			return implode(', ',$entries->lists('author', 'id'));
		}

		return null;
	}

	/**
	 * Run this when the field gets assigned
	 * @return void
	 */
	public function fieldAssignmentConstruct()
	{
		// Setup
		$this->setTable();

		// Duplicate our instance
		$instance = $this;

		// Get the schema
		$schema = ci()->pdb->getSchemaBuilder();

		// Drop any existing
		$schema->dropIfExists($this->table);

		/**
		 * Create our pivot table
		 */
		$schema->create($this->table, function($table) use ($instance) {
			$table->integer('entry_id');
			$table->integer('related_id');

			$table->index('entry_id');
			$table->index('related_id');
		});
	}

	/**
	 * Run this when the field gets unassigned
	 * @return void
	 */
	public function fieldAssignmentDestruct()
	{
		// Get our table name
		$this->setTable();

		// Get the schema
		$schema = ci()->pdb->getSchemaBuilder();

		// Drop it like it's hot
		$schema->dropIfExists($this->table);
	}

	/**
	 * Do this when the namespace is destroyed
	 * @return void
	 */
	public function namespaceDestruct()
	{
		// Get our table name
		$this->setTable();

		// Get the schema
		$schema = ci()->pdb->getSchemaBuilder();

		// Drop it like it's hot
		$schema->dropIfExists($this->table);
	}

	/**
	 * Ran when the entry is deleted
	 * @return void
	 */
	public function entryDestruct()
	{
		if (isset($this->entry->id)) {
			// Setup
			$this->setTable();

			// Delete by entry_id or related_id
			ci()->pdb
				->table($this->table)
				->where('entry_id', $this->entry->id)
				->orWhere('related_id', $this->entry->id)
				->delete();
		}
	}

	///////////////////////////////////////////////////////////////////////////////
	// -------------------------	UTILITIES 	  ------------------------------ //
	///////////////////////////////////////////////////////////////////////////////

	/**
	 * Create some shit
	 * @return string
	 */
	public function setTable()
	{
		// Table name
		$this->table = $this->stream->stream_prefix.$this->stream->stream_slug.'_'.$this->field->field_slug;
	}

	///////////////////////////////////////////////////////////////////////////////
	// -------------------------	PARAMETERS 	  ------------------------------ //
	///////////////////////////////////////////////////////////////////////////////

	/**
	 * Define the maximum amount of selection allowed
	 * @param  integer $value
	 * @return html
	 */
	public function paramMaxSelections($value = '')
	{
		return form_input('max_selections', $value);
	}

	/**
	 * Define the placeholder of the input
	 * @param  string $value
	 * @return html
	 */
	public function paramPlaceholder($value = '')
	{
		return form_input('placeholder', $value);
	}

	/**
	 * Define the field to use for values
	 * @param  string $value
	 * @return html
	 */
	public function paramValueField($value = '')
	{
		return form_input('value_field', $value);
	}

	/**
	 * Define the field to use for labels (options)
	 * @param  string $value
	 * @return html
	 */
	public function paramLabelField($value = '')
	{
		return form_input('label_field', $value);
	}

	/**
	 * Define the field to use for search
	 * @param  string $value
	 * @return html
	 */
	public function paramSearchField($value = '')
	{
		return form_input('search_field', $value);
	}

	/**
	 * Define any special template slug for this stream
	 * Loads like:
	 *  - views/field_types/TEMPLATE/option.php
	 *  - views/field_types/TEMPLATE/item.php
	 * @param  string $value
	 * @return html
	 */
	public function paramTemplate($value = '')
	{
		return form_input('template', $value);
	}

	/**
	 * Define an override of the module slug
	 * in case it is not the same as the namespace
	 * @param  string $value
	 * @return html
	 */
	public function paramModuleSlug($value = '')
	{
		return form_input('module_slug', $value);
	}

	///////////////////////////////////////////////////////////////////////////
	// -------------------------	AJAX 	  ------------------------------ //
	///////////////////////////////////////////////////////////////////////////

	public function ajaxSearch()
	{
		/**
		 * Determine the stream
		 */
		$stream = explode('.', ci()->uri->segment(7));
		$stream = Model\Stream::findBySlugAndNamespace($stream[0], $stream[1]);


		/**
		 * Determine our field / type
		 */
		$field = Model\Field::findBySlugAndNamespace(ci()->uri->segment(8), ci()->uri->segment(6));
		$field_type = $field->getType(null);


		/**
		 * Get our entries
		 */
		
		$fields = array_unique(
			array(
				$field_type->getParameter('value_field'),
				$field_type->getParameter('label_field'),
				$field_type->getParameter('search_field'),
				)
			);

		$entries = Model\Entry::stream($stream->stream_slug, $stream->stream_namespace)->select($fields)->where($field_type->getParameter('search_field'), 'LIKE', '%'.ci()->input->get('query').'%')->take(10)->get();


		/**
		 * Stash the title_column just in case nothing is defined later
		 */
		$entries = $entries->unformatted()->toArray();

		header('Content-type: application/json');
		echo json_encode(array('entries' => $entries));
	}
}
