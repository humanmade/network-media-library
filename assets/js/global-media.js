var gm = gm || {};

;( function( wp, $ ) {

	if ( ! wp || ! wp.media ) { return; }

	_.extend( gm, { model: {}, view: {}, controller: {}, frames: {} } );

	gm.controller.GlobalMediaLibary = wp.media.controller.Library.extend({
		defaults: _.defaults({
			id:            'global-media',
			title:         wp.media.view.l10n.globalMediaTitle,
			multiple:      true,
			editable:      false,
			content:       'global-media',
			filterable:    'uploaded',
			menu:          'default',
			toolbar:       'main-insert',
			router:        false,
			priority:      200,
			syncSelection: false,
			contentUserSetting: false,
			displayUserSettings: true,
			allowLocalEdits: true,
			library: wp.media.query({ 'global_media': true })
		}, wp.media.controller.Library.prototype.defaults ),
	});

	/**
	 * See wp.media.view.AttachmentsBrowser.
	 */
	gm.view.GlobalMediaBrowser = wp.media.View.extend({
		tagName:   'div',
		className: 'attachments-browser',

		initialize: function() {
			_.defaults( this.options, {
				display: false,
				AttachmentView: wp.media.view.Attachment.Library
			});

			this.createToolbar();
			this.createSidebar();
			this.createAttachments();
			this.updateContent();

			this.collection.on( 'add remove reset', this.updateContent, this );
		},

		/**
		 * @returns {wp.media.view.AttachmentsBrowser} Returns itself to allow chaining
		 */
		dispose: function() {
			this.options.selection.off( null, null, this );
			wp.media.View.prototype.dispose.apply( this, arguments );
			return this;
		},

		createToolbar: function() {
			var LibraryViewSwitcher, Filters, toolbarOptions;

			toolbarOptions = {
				controller: this.controller
			};

			/**
			* @member {wp.media.view.Toolbar}
			*/
			this.toolbar = new wp.media.view.Toolbar( toolbarOptions );

			this.views.add( this.toolbar );

			this.toolbar.set( 'spinner', new wp.media.view.Spinner({
				priority: -60
			}) );

			// "Filters" will return a <select>, need to render
			// screen reader text before
			this.toolbar.set( 'filtersLabel', new wp.media.view.Label({
				value: wp.media.view.l10n.filterByType,
				attributes: {
					'for':  'media-attachment-filters'
				},
				priority:   -80
			}).render() );

			this.toolbar.set( 'filters', new wp.media.view.AttachmentFilters.Uploaded({
				controller: this.controller,
				model:      this.collection.props,
				priority:   -80
			}).render() );

			// Search is an input, screen reader text needs to be rendered before
			this.toolbar.set( 'searchLabel', new wp.media.view.Label({
				value: wp.media.view.l10n.searchMediaLabel,
				attributes: {
					'for': 'media-search-input'
				},
				priority:   60
			}).render() );
			this.toolbar.set( 'search', new wp.media.view.Search({
				controller: this.controller,
				model:      this.collection.props,
				priority:   60
			}).render() );
		},

		updateContent: function() {
			var view = this;

			if ( ! this.collection.length ) {
				this.toolbar.get( 'spinner' ).show();
				this.dfd = this.collection.more().done( function() {
					view.toolbar.get( 'spinner' ).hide();
				} );
			} else {
				view.toolbar.get( 'spinner' ).hide();
			}
		},

		createAttachments: function() {
			this.attachments = new wp.media.view.Attachments({
				controller:           this.controller,
				collection:           this.collection,
				selection:            this.options.selection,
				model:                this.model,
				sortable:             this.options.sortable,
				scrollElement:        this.options.scrollElement,
				idealColumnWidth:     this.options.idealColumnWidth,

				// The single `Attachment` view to be used in the `Attachments` view.
				AttachmentView: this.options.AttachmentView
			});

			// Add keydown listener to the instance of the Attachments view
			this.attachments.listenTo( this.controller, 'attachment:keydown:arrow',     this.attachments.arrowEvent );
			this.attachments.listenTo( this.controller, 'attachment:details:shift-tab', this.attachments.restoreFocus );

			this.views.add( this.attachments );
		},

		createSidebar: function() {
			var options = this.options,
				selection = options.selection,
				sidebar = this.sidebar = new wp.media.view.Sidebar({
					controller: this.controller
				});

			this.views.add( sidebar );

			selection.on( 'selection:single', this.createSingle, this );
			selection.on( 'selection:unsingle', this.disposeSingle, this );

			if ( selection.single() ) {
				this.createSingle();
			}
		},

		createSingle: function() {
			var sidebar = this.sidebar,
				single = this.options.selection.single();

			sidebar.set( 'details', new wp.media.view.Attachment.Details({
				controller: this.controller,
				model:      single,
				priority:   80
			}) );

			sidebar.set( 'compat', new wp.media.view.AttachmentCompat({
				controller: this.controller,
				model:      single,
				priority:   120
			}) );

			sidebar.set( 'display', new wp.media.view.Settings.AttachmentDisplay({
				controller:   this.controller,
				model:        this.model.display( single ),
				attachment:   single,
				priority:     160,
				userSettings: this.model.get('displayUserSettings')
			}) );

			// Show the sidebar on mobile
			if ( this.model.id === 'insert' ) {
				sidebar.$el.addClass( 'visible' );
			}
		},

		disposeSingle: function() {
			var sidebar = this.sidebar;
			sidebar.unset('details');
			sidebar.unset('compat');
			sidebar.unset('display');
			// Hide the sidebar on mobile
			sidebar.$el.removeClass( 'visible' );
		}
	});

	// supersede the default MediaFrame.Post view
	var oldMediaFrame = wp.media.view.MediaFrame.Post;
	wp.media.view.MediaFrame.Post = oldMediaFrame.extend({

		initialize: function() {
			oldMediaFrame.prototype.initialize.apply( this, arguments );

			this.states.add([
				new gm.controller.GlobalMediaLibary()
			]);

			this.on( 'content:create:global-media', this.createGlobalMediaContent, this );
			this.on( 'content:render:global-media', this.renderGlobalMediaContent, this );
		},

		createGlobalMediaContent: function() {
			var state = this.state();

			this.globalMediaView = new gm.view.GlobalMediaBrowser({
				controller: this,
				collection: state.get('library'),
				selection:  state.get('selection'),
				model:      state,
			});
			this.content.set( this.globalMediaView );
		},

		renderGlobalMediaContent: function() {
			this.content.set( this.globalMediaView );
		}
	});

	wp.media.view.MediaFrame.Select = wp.media.view.MediaFrame.Select.extend({

	    initialize: function() {
	        wp.media.view.MediaFrame.prototype.initialize.apply( this, arguments );

	        _.defaults( this.options, {
	            multiple:  true,
	            editing:   false,
	            state:    'library'
	        });

	        this.createSelection();
	        this.createStates();
	        this.bindHandlers();
	    },

	    /**
	     * Bind region mode event callbacks.
	     *
	     * @see media.controller.Region.render
	     */
	    bindHandlers: function() {
	        this.on( 'router:create:browse', this.createRouter, this );
	        this.on( 'router:render:browse', this.browseRouter, this );
	        this.on( 'content:create:browse', this.browseContent, this );
	        this.on( 'content:render:upload', this.uploadContent, this );
	        this.on( 'toolbar:create:select', this.createSelectToolbar, this );
	        this.on( 'content:create:browseGlobalMedia', this.browseGlobalMediaContent, this );
	    },

	    /**
	     * Render callback for the router region in the `browse` mode.
	     *
	     * @param {wp.media.view.Router} routerView
	     */
	    browseRouter: function( routerView ) {
	        routerView.set({
	            upload: {
	                text:     wp.media.view.l10n.uploadFilesTitle,
	                priority: 20
	            },
	            browse: {
	                text:     wp.media.view.l10n.mediaLibraryTitle,
	                priority: 40
	            },
	            browseGlobalMedia: {
	                text:     'Global Media',
	                priority: 60
	            },
	        });
	    },

	    /**
	     * Render callback for the content region in the `browse` mode.
	     *
	     * @param {wp.media.controller.Region} contentRegion
	     */
	    browseContent: function( contentRegion ) {
	        var state = this.state();
	        var options = this.options;


	        state.set( 'library', wp.media.query(_.defaults({

	            }, options.library )) );


	        wp.media.controller.Library.prototype.initialize.apply( state, arguments );

	        this.$el.removeClass('hide-toolbar');

	        // Browse our library of attachments.
	        contentRegion.view = new wp.media.view.AttachmentsBrowser({
	            controller: this,
	            collection: state.get('library'),
	            selection:  state.get('selection'),
	            model:      state,
	            sortable:   state.get('sortable'),
	            search:     state.get('searchable'),
	            filters:    state.get('filterable'),
	            date:       state.get('date'),
	            display:    state.has('display') ? state.get('display') : state.get('displaySettings'),
	            dragInfo:   state.get('dragInfo'),

	            idealColumnWidth: state.get('idealColumnWidth'),
	            suggestedWidth:   state.get('suggestedWidth'),
	            suggestedHeight:  state.get('suggestedHeight'),

	            AttachmentView: state.get('AttachmentView')
	        });
	    },

	    /**
	     * Render callback for the content region in the `browse` mode.
	     *
	     * @param {wp.media.controller.Region} contentRegion
	     */
	    browseGlobalMediaContent: function( contentRegion ) {
	        var state = this.state();
	        var options = this.options;


	        state.set( 'library', wp.media.query(_.defaults({
	                // Adding a new query parameter
	                'global_media': true,

	            }, options.library )) );


	        wp.media.controller.Library.prototype.initialize.apply( state, arguments );

	        this.$el.removeClass('hide-toolbar');

	        // Browse our library of attachments.
	        contentRegion.view = new wp.media.view.AttachmentsBrowser({
	            controller: this,
	            collection: state.get('library'),
	            selection:  state.get('selection'),
	            model:      state,
	            sortable:   state.get('sortable'),
	            search:     state.get('searchable'),
	            filters:    state.get('filterable'),
	            date:       state.get('date'),
	            display:    state.has('display') ? state.get('display') : state.get('displaySettings'),
	            dragInfo:   state.get('dragInfo'),

	            idealColumnWidth: state.get('idealColumnWidth'),
	            suggestedWidth:   state.get('suggestedWidth'),
	            suggestedHeight:  state.get('suggestedHeight'),

	            AttachmentView: state.get('AttachmentView')
	        });
	    },
	});

} )( window.wp, jQuery );
