<?php namespace Pyro\FieldType;

use Pyro\Module\Streams_core\EntryModel;
use Pyro\Module\Streams_core\StreamModel;
use Pyro\Module\Streams_core\FieldModel;
use Pyro\Module\Streams_core\AbstractFieldType;

/**
 * Streams Entry Selector Field Type
 *
 * @package		PyroCMS\Addons\Shared Addons\Field Types
 */
class Multiple extends AbstractFieldType
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
	 * Runtime funtime cache
	 * @var array
	 */
	public $runtime_cache = array(
		'pluginOutput' => array(),
		);

	///////////////////////////////////////////////////////////////////////////////
	// --------------------------	METHODS 	  ------------------------------ //
	///////////////////////////////////////////////////////////////////////////////

	/**
	 * Run before form is built
	 *
	 * @return	void
	 */
	public function event()
	{
		$this->appendMetadata($this->view('fragments/multiple.js.php'));
	}

	/**
	 * Run before table is built
	 *
	 * @return	void
	 */
	public function filterEvent()
	{
		$this->appendMetadata($this->view('fragments/multiple.js.php'));
	}

	/**
	 * Relation
	 * @return object The relation object
	 */
	public function relation()
	{
		// Crate our runtime cache hash
		$hash = md5($this->stream->stream_slug.$this->stream->stream_namespace.$this->field->field_slug.$this->value);

		// Check / retreive hashed storage
		if (! isset($this->runtime_cache[$hash])) {
			$this->runtime_cache[$hash] = $this->belongsToManyEntries($this->getParameter('relation_class', 'Pyro\Module\Streams_core\EntryModel'))->select('*');
		}

		return $this->runtime_cache[$hash];
	}

	/**
	 * Output form input
	 *
	 * @access 	public
	 * @return	string
	 */
	public function formInput()
	{
		// Entry options
		$options = $this->getRelationResult();

		// Gather Ids
		$value = array();

		foreach ($options as $option)
			$value[] = $option->id;

		// To array
		if ($options) $options = $options->toArray(); else array();

		// Data
		$data = '
			data-options="'.htmlentities(json_encode($options)).'"
			data-value="'.htmlentities(implode(',', $value)).'"
			data-form_slug="'.$this->form_slug.'"
			data-field_slug="'.$this->field->field_slug.'"
			data-stream_param="'.$this->getParameter('stream').'"
			data-stream_namespace="'.$this->stream->stream_namespace.'"
			
			data-max_selections="'.$this->getParameter('max_selections', '1').'"

			data-value_field="'.$this->getParameter('value_field', 'id').'"
			data-label_field="'.$this->getParameter('label_field', '_title_column').'"
			data-search_field="'.$this->getParameter('search_field', '_title_column').'"
			
			id="'.$this->form_slug.'"
			class="skip selectize-multiple"
			placeholder="'.lang_label($this->getParameter('placeholder', 'lang:streams:multiple.placeholder')).'"
			';

		// Start the HTML
		return form_dropdown(
			$this->form_slug.'[]',
			array(),
			null,
			$data
			);
	}

	/**
	 * Output filter input
	 *
	 * @access 	public
	 * @return	string
	 */
	public function filterInput()
	{
		// Entry options
		$options = $this->getRelationResult();

		// To array
		if ($options) $options = $options->toArray(); else array();

		// Data
		$data = '
			data-options="'.htmlentities(json_encode($options)).'"
			data-form_slug="'.$this->getFilterSlug('is').'"
			data-field_slug="'.$this->field->field_slug.'"
			data-stream_param="'.$this->getParameter('stream').'"
			data-stream_namespace="'.$this->stream->stream_namespace.'"
			
			data-max_selections="1"

			data-value_field="'.$this->getParameter('value_field', 'id').'"
			data-label_field="'.$this->getParameter('label_field', '_title_column').'"
			data-search_field="'.$this->getParameter('search_field', '_title_column').'"
			
			id="'.$this->form_slug.'"
			class="skip selectize-multiple"
			placeholder="'.lang_label($this->getParameter('placeholder', 'lang:streams:multiple.placeholder')).'"
			';

		// Start the HTML
		return form_dropdown(
			$this->getFilterSlug('is'),
			array(),
			null,
			$data
			);
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

		foreach ((array) ci()->input->post($this->form_slug) as $id) {

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
	 * Pre Ouput String
	 *
	 * @return	mixed 	null or string
	 */
	public function stringOutput()
	{
		if($entries = $this->getValueEntries() and ! empty($entries))
		{
			return implode(', ', $entries);
		}

		return null;
	}

	/**
	 * Pre Ouput Plugin
	 *
	 * @return	mixed 	null or array
	 */
	public function pluginOutput()
	{
		// Crate our runtime cache hash
		$hash = md5($this->stream->stream_slug.$this->stream->stream_namespace.$this->field->field_slug.$this->value);

		if (! isset($this->runtime_cache['pluginOutput'][$hash])) {
			if ($entry = $this->getRelationResult() and ! empty($entries))
			{
				return $this->runtime_cache['pluginOutput'][$hash] = $entries->asPlugin()->toArray();
			} else {
				return $this->runtime_cache['pluginOutput'][$hash] = null;
			}
		} else {
			return $this->runtime_cache['pluginOutput'][$hash];
		}
	}

	/**
	 * Pre Ouput Data
	 *
	 * @return	mixed 	null or array
	 */
	public function dataOutput()
	{
		if($entries = $this->getRelationResult() and ! empty($entries))
		{
			return $entries;
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
		$stream = StreamModel::findBySlugAndNamespace($stream[0], $stream[1]);


		/**
		 * Determine our field / type
		 */
		$field = FieldModel::findBySlugAndNamespace(ci()->uri->segment(8), ci()->uri->segment(6));
		$field_type = $field->getType();


		/**
		 * Determine our select
		 */
		$select = array_unique(
			array_merge(
				array_values(explode('|', $field->getParameter('value_field', 'id'))),
				array_values(explode('|', $field->getParameter('label_field'))),
				array_values(explode('|', $field->getParameter('search_field')))
				)
			);


		/**
		 * Get our entries
		 */
		$entries = EntryModel::stream($stream->stream_slug, $stream->stream_namespace)->select($select)->where($field_type->getParameter('search_field'), 'LIKE', '%'.ci()->input->get('query').'%')->take(10)->get();


		/**
		 * Stash the title_column just in case nothing is defined later
		 */
		$entries = $entries->toArray();

		header('Content-type: application/json');
		echo json_encode(array('entries' => $entries));
	}

	///////////////////////////////////////////////////////////////////////////////
	// -------------------------	UTILITIES 	  ------------------------------ //
	///////////////////////////////////////////////////////////////////////////////

	/**
	 * Get values for dropdown
	 * @param  mixed $value string or bool
	 * @return array
	 */
	protected function getValueEntries($value = false)
	{
		// Break apart the stream
		$stream = explode('.', $this->getParameter('stream'));
		$stream = StreamModel::findBySlugAndNamespace($stream[0], $stream[1]);

		// Boom
		return $this->getRelationResult()->getEntryOptions($stream->title_column);
	}
}
