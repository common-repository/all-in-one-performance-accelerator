if ( window.jQuery ) {

	jQuery( function($) {
		var toolbarHeight          = $('#wpadminbar').length ? $('#wpadminbar').outerHeight() : 0;
		var minheight              = 100;
		var maxheight              = ( $(window).height() - toolbarHeight );
		var minwidth               = 300;
		var maxwidth               = $(window).width();
		var container              = $('#query-view');
		var body                   = $('body');
		var body_margin            = body.css('margin-bottom');
		var container_height_key   = 'smack-container-height';
		var container_pinned_key   = 'smack-' + ( $('body').hasClass('wp-admin') ? 'admin' : 'front' ) + '-container-pinned';
		var container_position_key = 'smack-container-position';
		var container_width_key    = 'smack-container-width';

		if ( container.hasClass('smack-peek') ) {
			minheight = 27;
		}

		container.removeClass('smack-no-js').addClass('smack-js');

		if ( $('#smack-fatal').length ) {
			console.error(qm_l10n.fatal_error + ': ' + $('#smack-fatal').attr('data-smack-message') );

			if ( $('#wp-admin-bar-performance-enhancer').length ) {
				$('#wp-admin-bar-performance-enhancer')
					.addClass('smack-error')
					.find('a').eq(0)
					.text(qm_l10n.fatal_error);

				var fatal_container = document.createDocumentFragment();

				var fatal_message_menu = $('##wp-admin-bar-smack-query')
					.clone()
					.attr('id','wp-admin-bar-smack-fatal-message');

				fatal_message_menu
					.find('a').eq(0)
					.text($('#smack-fatal').attr('data-smack-message'))
					.attr('href','#smack-fatal');

				fatal_container.appendChild( fatal_message_menu.get(0) );

				var fatal_file_menu = $('##wp-admin-bar-smack-query')
					.clone()
					.attr('id','wp-admin-bar-smack-fatal-file');

				fatal_file_menu
					.find('a').eq(0)
					.text($('#smack-fatal').attr('data-smack-file') + ':' + $('#smack-fatal').attr('data-smack-line'))
					.attr('href','#smack-fatal');

				fatal_container.appendChild( fatal_file_menu.get(0) );

				$('#wp-admin-bar-performance-enhancer ul').append(fatal_container);
			}
		}

		var link_click = function(e){
			var href = $( this ).attr('href') || $( this ).data('smack-href');

			if ( '#smack-fatal' === href ) {
				return;
			}

			show_panel( href );
			$(href).focus();
			$('#wp-admin-bar-performance-enhancer').removeClass('hover');
			e.preventDefault();
		};

		var stripes = function( table ) {
			table.each(function() {
				$(this).find('tbody tr').removeClass('smack-odd').not('[class*="smack-hide"]').filter(':even').addClass('smack-odd');
			} );
		};

		var show_panel = function( panel ) {
			container.addClass('smack-show').removeClass('smack-hide');
			$( '.smack' ).removeClass('smack-panel-show');
			$('#smack-panels').scrollTop(0);
			$( panel ).addClass('smack-panel-show');

			if ( container.height() < minheight ) {
				container.height( minheight );
			}

			if ( container.hasClass('smack-show-right') ) {
				body.css( 'margin-bottom', '' );
			} else {
				body.css( 'margin-bottom', 'calc( ' + body_margin + ' + ' + container.height() + 'px )' );
			}

			$('#smack-panel-menu').find('button').removeAttr('aria-selected');
			$('#smack-panel-menu').find('li').removeClass('smack-current-menu');
			var selected_menu = $('#smack-panel-menu').find('[data-smack-href="' + panel + '"]').attr('aria-selected',true);

			if ( selected_menu.length ) {
				var selected_menu_top = selected_menu.position().top - 27;
				var menu_height       = $('#smack-panel-menu').height();
				var menu_scroll       = $('#smack-panel-menu').scrollTop();
				selected_menu.closest('#smack-panel-menu > ul > li').addClass('smack-current-menu');

				var selected_menu_off_bottom = ( selected_menu_top > ( menu_height ) );
				var selected_menu_off_top    = ( selected_menu_top < 0 );

				if ( selected_menu_off_bottom || selected_menu_off_top ) {
					$('#smack-panel-menu').scrollTop( selected_menu_top + menu_scroll - ( menu_height / 2 ) + ( selected_menu.outerHeight() / 2 ) );
				}
			}

			$('.smack-title-heading select').val(panel);

			localStorage.setItem( container_pinned_key, panel );

			var filters = $( panel ).find('.smack-filter');

			if ( filters.length ) {
				filters.trigger('change');
			} else {
				stripes( $(panel).find('table') );
			}

		};

		if ( $('#wp-admin-bar-performance-enhancer').length ) {

			var admin_bar_menu_container = document.createDocumentFragment();

			if ( window.smack && window.smack.menu ) {
				$('#wp-admin-bar-performance-enhancer')
					.addClass(smack.menu.top.classname)
					.attr('dir','ltr')
					.find('a').eq(0)
					.html(smack.menu.top.title);

				$.each( smack.menu.sub, function( i, el ) {

					var new_menu = $('##wp-admin-bar-smack-query')
						.clone()
						.attr('id','wp-admin-bar-' + el.id);
					new_menu
						.find('a').eq(0)
						.html(el.title)
						.attr('href',el.href);

					if ( ( typeof el.meta != 'undefined' ) && ( typeof el.meta.classname != 'undefined' ) ) {
						new_menu.addClass(el.meta.classname);
					}

					admin_bar_menu_container.appendChild( new_menu.get(0) );

				} );

				$('#wp-admin-bar-performance-enhancer ul').append(admin_bar_menu_container);
			}

			$('#wp-admin-bar-performance-enhancer').find('a').on('click',link_click);

			$('#wp-admin-bar-performance-enhancer,#wp-admin-bar-performance-enhancer-default').show();

		} else {
			container.addClass('smack-peek').removeClass('smack-hide');
			$('#smack-overview').addClass('smack-panel-show');
		}

		$('#wp-admin-bar-performance-enhancer').find('a').on('click',function(e){
			var href = $( this ).attr('href') || $( this ).data('smack-href');
			
			if(href == '#asset-view'){
				$('.smack_asset_view').css('display','block')
				$('.smack_query_table_view').css('display','none')
			}else{
				$('.smack_asset_view').css('display','none')
				$('.smack_query_table_view').css('display','block')
			}
		});

		$(".smack_button").click(function() {
			console.log("smackc_button",this.id);
			var fired_button = 'smack-panels-'+this.id;
			var fields = $('.smack-panels');
			$.each(fields, function( key, value ) {
				if(value.id == fired_button){
			    	$('#'+fired_button).css('display','block');
				}else{
					$('#'+value.id).css('display','none');
				}
			});
		});

		$(".data-script").click(function() {
			var fired_button = $('.data-script').find('data-script');
			var script_name = this.dataset.script;
			var script_type = this.dataset.type;
			var core_type = this.dataset.scripttype;
			var checked_value = this.checked;
			var url = this.dataset.url;
			jQuery.ajax({
				type: 'POST',
				url : ajaxurl,
				data : {
					action : 'dequeue_styles',
					checked : checked_value,
					coretype : core_type,
					script_type : script_type,
					scriptname : script_name,
					url : url,
				} ,
				
				success: function(){
					
				},
				error: function(errorThrown){
					
				}
				
			});
		});

		$('#smack-panel-menu').find('button').on('click',link_click);

		container.find('.smack-filter').on('change',function(e){

			var filter = $(this).attr('data-filter'),
				table  = $(this).closest('table'),
				tr     = table.find('tbody tr'),
				// Escape the following chars with a backslash before passing into jQ selectors: [ ] ( ) ' " \
				val    = $(this).val().replace(/[[\]()'"\\]/g, "\\$&"),
				total  = tr.removeClass('smack-hide-' + filter).length,
				hilite = $(this).attr('data-highlight'),
				time   = 0;

			key = $(this).attr('id');
			if ( val ) {
				localStorage.setItem( key, $(this).val() );
			} else {
				localStorage.removeItem( key );
			}

			if ( hilite ) {
				table.find('tr').removeClass('smack-highlight');
			}

			if ( $(this).val() !== '' ) {
				if ( hilite ) {
					tr.filter('[data-smack-' + hilite + '*="' + val + '"]').addClass('smack-highlight');
				}
				tr.not('[data-smack-' + filter + '*="' + val + '"]').addClass('smack-hide-' + filter);
				$(this).closest('th').addClass('smack-filtered');
			} else {
				$(this).closest('th').removeClass('smack-filtered');
			}

			var matches = tr.filter(':visible');
			matches.each(function(i){
				var row_time = $(this).attr('data-smack-time');
				if ( row_time ) {
					time += parseFloat( row_time );
				}
			});
			if ( time ) {
				time = QM_i18n.number_format( time, 4 );
			}

			if ( table.find('.smack-filtered').length ) {
				var count = matches.length + ' / ' + tr.length;
			} else {
				var count = matches.length;
			}

			table.find('.smack-items-number').text(count);
			table.find('.smack-items-time').text(time);

			stripes(table);
		});

		container.find('.smack-filter').each(function () {
			var key   = $(this).attr('id');
			var value = localStorage.getItem( key );
			if ( value !== null ) {
				// Escape the following chars with a backslash before passing into jQ selectors: [ ] ( ) ' " \
				var val = value.replace(/[[\]()'"\\]/g, "\\$&");
				if ( ! $(this).find('option[value="' + val + '"]').length ) {
					$('<option>').attr('value',value).text(value).appendTo(this);
				}
				$(this).val(value).trigger('change');
			}
		});

		container.find('.smack-filter-trigger').on('click',function(e){
			var filter = $(this).data('smack-filter'),
				value  = $(this).data('smack-value'),
				target = $(this).data('smack-target');
			$('#smack-' + target).find('.smack-filter').not('[data-filter="' + filter + '"]').val('').removeClass('smack-highlight').trigger('change');
			$('#smack-' + target).find('[data-filter="' + filter + '"]').val(value).addClass('smack-highlight').trigger('change');
			show_panel( '#smack-' + target );
			$('#smack-' + target).focus();
			e.preventDefault();
		});

		container.find('.smack-toggle').on('click',function(e){
			var el           = $(this);
			var currentState = el.attr('aria-expanded');
			var newState     = 'true';
			if (currentState === 'true') {
				newState = 'false';
			}
			el.attr('aria-expanded', newState);
			var toggle = $(this).closest('td').find('.smack-toggled');
			if ( currentState === 'true' ) {
				if ( toggle.length ) {
					toggle.slideToggle(200,function(){
						el.closest('td').removeClass('smack-toggled-on');
						el.text(el.attr('data-on'));
					});
				} else {
					el.closest('td').removeClass('smack-toggled-on');
					el.text(el.attr('data-on'));
				}
			} else {
				el.closest('td').addClass('smack-toggled-on');
				el.text(el.attr('data-off'));
				toggle.slideToggle(200);
			}
			e.preventDefault();
		});

		container.find('.smack-highlighter').on('mouseenter',function(e){

			var subject = $(this).data('smack-highlight');
			var table   = $(this).closest('table');

			if ( ! subject ) {
				return;
			}

			$(this).addClass('smack-highlight');

			$.each( subject.split(' '), function( i, el ){
				table.find('tr[data-smack-subject="' + el + '"]').addClass('smack-highlight');
			});

		}).on('mouseleave',function(e){

			$(this).removeClass('smack-highlight');
			$(this).closest('table').find('tr').removeClass('smack-highlight');

		});

		$('.smack').find('tbody a,tbody button').on('focus',function(e){
			$(this).closest('tr').addClass('smack-hovered');
		}).on('blur',function(e){
			$(this).closest('tr').removeClass('smack-hovered');
		});

		container.find('.smack table').on('sorted.smack',function(){
			stripes( $(this) );
		});

		$( document ).ajaxSuccess( function( event, response, options ) {

			var errors = response.getResponseHeader( 'X-smack-php_errors-error-count' );

			if ( ! errors ) {
				return event;
			}

			errors = parseInt( errors, 10 );

			if ( window.console ) {
				console.group( qm_l10n.ajax_error );
			}

			for ( var key = 1; key <= errors; key++ ) {

				error = JSON.parse( response.getResponseHeader( 'X-smack-php_errors-error-' + key ) );

				if ( window.console ) {
					switch ( error.type ) {
						case 'warning':
							console.error( error );
							break;
						default:
							console.warn( error );
							break;
					}
				}

				if ( $('#smack-php_errors').find('[data-smack-key="' + error.key + '"]').length ) {
					continue;
				}

				if ( $('#wp-admin-bar-performance-enhancer').length ) {
					if ( ! smack.ajax_errors[error.type] ) {
						$('#wp-admin-bar-performance-enhancer')
							.addClass('smack-' + error.type)
							.find('a').first().append('<span class="ab-label smack-ajax-' + error.type + '"> &nbsp; Ajax: ' + error.type + '</span>');
					}
				}

				smack.ajax_errors[error.type] = true;

			}

			if ( window.console ) {
				console.groupEnd();
			}

			$( '#smack-ajax-errors' ).show();

			return event;

		} );

		$('.smack-auth').on('click',function(e){
			var state  = $('#smack-settings').data('smack-state');
			var action = ( 'off' === state ? 'on' : 'off' );

			$.ajax(qm_l10n.ajaxurl,{
				type : 'POST',
				context : this,
				data : {
					action : 'qm_auth_' + action,
					nonce  : qm_l10n.auth_nonce[action]
				},
				success : function(response){
					$(this).text( $(this).data('smack-text-' + action) );
					$('#smack-settings').attr('data-smack-state',action).data('smack-state',action);
				},
				dataType : 'json',
				xhrFields: {
					withCredentials: true
				}
			});

			e.preventDefault();
		});

		var editorSuccessIndicator = $('#smack-editor-save-status');
		editorSuccessIndicator.hide();

		$('.smack-editor-button').on('click',function(e){
			var state  = $('#smack-settings').data('smack-state');
			var editor = $('#smack-editor-select').val();

			$.ajax(qm_l10n.ajaxurl,{
				type : 'POST',
				context : this,
				data : {
					action : 'qm_editor_set',
					nonce  : qm_l10n.auth_nonce['editor-set'],
					editor : editor
				},
				success : function(response){
					if (response.success) {
						editorSuccessIndicator.show();
					}
				},
				dataType : 'json',
				xhrFields: {
					withCredentials: true
				}
			});

			e.preventDefault();
		});

		$.smack.tableSort({target: $('.smack-sortable')});

		var startY, startX, resizerHeight;

		$(document).on('mousedown touchstart', '.smack-resizer', function(event) {
			event.stopPropagation();

			resizerHeight = $(this).outerHeight() - 1;
			startY        = container.outerHeight() + ( event.clientY || event.originalEvent.targetTouches[0].pageY );
			startX        = container.outerWidth() + ( event.clientX || event.originalEvent.targetTouches[0].pageX );

			if ( ! container.hasClass('smack-show-right') ) {
				$(document).on('mousemove touchmove', smack_do_resizer_drag_vertical);
			} else {
				$(document).on('mousemove touchmove', smack_do_resizer_drag_horizontal);
			}

			$(document).on('mouseup touchend', smack_stop_resizer_drag);
		});

		function smack_do_resizer_drag_vertical(event) {
				var h = ( startY - ( event.clientY || event.originalEvent.targetTouches[0].pageY ) );
				if ( h >= resizerHeight && h <= maxheight ) {
					container.height( h );
					body.css( 'margin-bottom', 'calc( ' + body_margin + ' + ' + h + 'px )' );
				}
		}

		function smack_do_resizer_drag_horizontal(event) {
				var w = ( startX - event.clientX );
				if ( w >= minwidth && w <= maxwidth ) {
					container.width( w );
				}
				body.css( 'margin-bottom', '' );
		}

		function smack_stop_resizer_drag(event) {
			$(document).off('mousemove touchmove', smack_do_resizer_drag_vertical);
			$(document).off('mousemove touchmove', smack_do_resizer_drag_horizontal);
			$(document).off('mouseup touchend', smack_stop_resizer_drag);

			if ( ! container.hasClass('smack-show-right') ) {
				localStorage.removeItem( container_position_key );
				localStorage.setItem( container_height_key, container.height() );
			} else {
				localStorage.setItem( container_position_key, 'right' );
				localStorage.setItem( container_width_key, container.width() );
			}
		}

		var p = localStorage.getItem( container_position_key );
		var h = localStorage.getItem( container_height_key );
		var w = localStorage.getItem( container_width_key );
		if ( ! container.hasClass('smack-peek') ) {
			if ( p === 'right' ) {
				if ( w !== null ) {
					if ( w < minwidth ) {
						w = minwidth;
					}
					if ( w > maxwidth ) {
						w = maxwidth;
					}
					container.width( w );
				}
				container.addClass('smack-show-right');
			} else if ( p !== 'right' && h !== null ) {
				if ( h < minheight ) {
					h = minheight;
				}
				if ( h > maxheight ) {
					h = maxheight;
				}
				container.height( h );
			}
		}

		$(window).on('resize', function(){
			var h         = container.height();
			var w         = container.width();

			maxheight = ( $(window).height() - toolbarHeight );
			maxwidth  = $(window).width();

			if ( h < minheight ) {
				container.height( minheight );
			}
			if ( h > maxheight ) {
				container.height( maxheight );
			}
			localStorage.setItem( container_height_key, container.height() );

			if ( w > $(window).width() ) {
				container.width( minwidth );
				localStorage.setItem( container_width_key, container.width() );
			}
			if ( $(window).width() < 960 ) {
				container.removeClass('smack-show-right');
				localStorage.removeItem( container_position_key );
			}
		});

		$('.smack-button-container-close').on('click',function(){
			container.removeClass('smack-show').height('').width('');
			body.css( 'margin-bottom', '' );
			localStorage.removeItem( container_pinned_key );
		});

		$('.smack-button-container-settings,a[href="#smack-settings"]').on('click',function(){
			show_panel( '#smack-settings' );
			$('#smack-settings').focus();
		});

		$('.smack-button-container-position').on('click',function(){
			container.toggleClass('smack-show-right');

			if ( container.hasClass('smack-show-right') ) {
				var w = localStorage.getItem( container_width_key );

				if ( w !== null && w < $(window).width() ) {
					container.width( w );
				}

				body.css( 'margin-bottom', '' );

				localStorage.setItem( container_position_key, 'right' );
			} else {
				body.css( 'margin-bottom', 'calc( ' + body_margin + ' + ' + container.height() + 'px )' );

				localStorage.removeItem( container_position_key );
			}
		});

		var pinned = localStorage.getItem( container_pinned_key );
		if ( pinned && $( pinned ).length ) {
			show_panel( pinned );
		}

		$('.smack-title-heading select').on('change',function(){
			show_panel( $(this).val() );
			$($(this).val()).focus();
		});

	} );

	/**
	 * Table sorting library.
	 *
	 * This is a modified version of jQuery table-sort v0.1.1
	 * https://github.com/gajus/table-sort
	 *
	 * Licensed under the BSD.
	 * https://github.com/gajus/table-sort/blob/master/LICENSE
	 *
	 * Author: Gajus Kuizinas <g.kuizinas@anuary.com>
	 */
	(function ($) {
		$.smack           = $.smack || {};
		$.smack.tableSort = function (settings) {
			// @param	object	columns	NodeList table colums.
			// @param	integer	row_width	defines the number of columns per row.
			var table_to_array = function (columns, row_width) {
				columns = Array.prototype.slice.call(columns, 0);

				var rows      = [];
				var row_index = 0;

				for (var i = 0, j = columns.length; i < j; i += row_width) {
					var row	= [];

					for (var k = 0; k < row_width; k++) {
						var e    = columns[i + k];
						var data = e.dataset.smackSortWeight;

						if (data === undefined) {
							data = e.textContent || e.innerText;
						}

						var number = parseFloat(data);

						data = isNaN(number) ? data : number;

						row.push(data);
					}

					rows.push({index: row_index++, data: row});
				}

				return rows;
			};

			if ( ! settings.target || ! ( settings.target instanceof $) ) {
				throw 'Target is not defined or it is not instance of jQuery.';
			}

			settings.target.each(function () {
				var table = $(this);

				table.find('.smack-sortable-column').on('click', function (e) {
					var desc  = ! $(this).hasClass('smack-sorted-desc');
					var index = $(this).index();

					table.find('thead th').removeClass('smack-sorted-asc smack-sorted-desc').attr('aria-sort','none');

					if ( desc ) {
						$(this).addClass('smack-sorted-desc').attr('aria-sort','descending');
					} else {
						$(this).addClass('smack-sorted-asc').attr('aria-sort','ascending');
					}

					table.find('tbody').each(function () {
						var tbody   = $(this);
						var rows    = this.rows;
						var columns = this.querySelectorAll('th,td');

						if (this.data_matrix === undefined) {
							this.data_matrix = table_to_array(columns, $(rows[0]).find('th,td').length);
						}

						var data = this.data_matrix;

						data.sort(function (a, b) {
							if (a.data[index] == b.data[index]) {
								return 0;
							}

							return (desc ? a.data[index] > b.data[index] : a.data[index] < b.data[index]) ? -1 : 1;
						});

						// Detach the tbody to prevent unnecessary overhead related
						// to the browser environment.
						tbody = tbody.detach();

						// Convert NodeList into an array.
						rows = Array.prototype.slice.call(rows, 0);

						var last_row = rows[data[data.length - 1].index];

						for (var i = 0, j = data.length - 1; i < j; i++) {
							tbody[0].insertBefore(rows[data[i].index], last_row);

							// Restore the index.
							data[i].index = i;
						}

						// Restore the index.
						data[data.length - 1].index = data.length - 1;

						table.append(tbody);
					});

					table.trigger('sorted.smack');

					e.preventDefault();
				});
			});
		};
	})(jQuery);

}

window.addEventListener('load', function() {
	var main = document.getElementById( 'query-view' );
	var broken = document.getElementById( 'smack-broken' );
	var menu_item = document.getElementById( 'wp-admin-bar-smack-query-monitor' );

	if ( ( 'undefined' === typeof jQuery ) || ! window.jQuery ) {
		/* Fallback for running without jQuery (`QM_NO_JQUERY`) or when jQuery is broken */

		if ( main ) {
			main.className += ' smack-broken';
		}

		if ( broken ) {
			console.error( broken.textContent );
		}

		if ( 'undefined' === typeof jQuery ) {
			console.error( 'QM error from JS: undefined jQuery' );
		} else if ( ! window.jQuery ) {
			console.error( 'QM error from JS: no jQuery' );
		}

		if ( menu_item && main ) {
			menu_item.addEventListener( 'click', function() {
				main.className += ' smack-show';
			} );
		}
	}

	if ( ! main ) {
		// QM's output has disappeared
		console.error( 'QM error from JS: QM output does not exist' );
	}
} );
window.onload = () => {
    LazyLoad();
}
window.addEventListener("scroll", function() {
    LazyLoad();
})
function LazyLoad(){
    const imageObserver = new IntersectionObserver((entries, imgObserver) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                const lazyImage = entry.target
                if(lazyImage.dataset.srcset){
                    lazyImage.srcset = lazyImage.dataset.srcset
                }
                if(lazyImage.dataset.src){
                    lazyImage.src = lazyImage.dataset.src
                }
                imgObserver.unobserve(lazyImage);
            }
        })
    });
    const arr = document.querySelectorAll('iframe,img');
    arr.forEach((v) => {
        if(v.classList.contains('lazy')){
            v.classList.remove('lazy');
        }
        imageObserver.observe(v);
    }) 
}