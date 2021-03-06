<?php
header('Content-type: text/javascript');

/**
*
* License:
*
* Copyright (c) 2003-2006 ossim.net
* Copyright (c) 2007-2013 AlienVault
* All rights reserved.
*
* This package is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; version 2 dated June, 1991.
* You may not use, modify or distribute this program under any other version
* of the GNU General Public License.
*
* This package is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this package; if not, write to the Free Software
* Foundation, Inc., 51 Franklin St, Fifth Floor, Boston,
* MA  02110-1301  USA
*
*
* On Debian GNU/Linux systems, the complete text of the GNU General
* Public License can be found in `/usr/share/common-licenses/GPL-2'.
*
* Otherwise you can read it here: http://www.gnu.org/licenses/gpl-2.0.txt
*
*/
require_once 'av_init.php';
?>


function Av_service_list(config)
{
    //Public variables;
    this.edit_mode = config.edit_mode;

    this.list_type = 'single';

    this.dt_obj = {};

    this.db = new av_session_db('db_services_' + uniqid());


    //Private variables

    //Prefix used to build ID attribute (Inputs, tables, span, ...)
    var __prefix = 'ts';

    var __msg_container = 'ts_av_info';

    //Messages to show
    var __messages = {
        "selected_rows"       : "<?php echo _('You have selected ### services.')?>",
        "select_all_rows"     : "<?php echo _('Select ### services.')?>",
        "delete_one"          : "<?php echo Util::js_entities(_('Are you sure you want to delete this service?'))?>",
        "delete_selected"     : "<?php echo Util::js_entities(_('Are you sure you want to delete the selected services?'))?>",
        'monitoring_enabled'  : "<?php echo Util::js_entities(_('Availability monitoring will be enabled for selected services/ports. Do you want to continue?'))?>",
        'monitoring_disabled' : "<?php echo Util::js_entities(_('Availability monitoring will be disabled for selected services/ports. Do you want to continue?'))?>",
        "confirm_yes"         : "<?php echo _('Yes')?>",
        "confirm_no"          : "<?php echo _('No')?>",
        "unknown_error"       : "<?php echo _('Sorry, operation was not completed due to an error when processing the request. Please try again')?>"
    };


    var __selection = {
        "type"   : "manual",
        "filter" : ""
    };

    //Callbacks to execute
    var __action_callbacks = config.action_callbacks;

    //Asset data
    var __asset_data = config.asset_data;

    //Data providers
    var __providers  = config.providers

    //Data controllers
    var __controllers  = config.controllers

    //Copy of this
    var __self = this;


    /**************************************************************************/
    /***************************  DRAW FUNCTIONS  *****************************/
    /**************************************************************************/


    this.draw = function()
    {
        var dt_parameters  = __get_dt_parameters();
        var aaSorting      = dt_parameters.sort;
        var aoColumns      = dt_parameters.columns;
        var fnServerParams = dt_parameters.server_params;


        __self.dt_obj = $('#table_data_services').dataTable(
        {
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": __providers.datatable,
            "fnServerParams": function (aoData) {
                $.each(fnServerParams, function(index, value) {
                    aoData.push(value);
                });
            },
            "sServerMethod" : "POST",
            "iDisplayLength" : 8,
            "sPaginationType" : "full_numbers",
            "bLengthChange" : false,
            "bJQueryUI" : true,
            "aaSorting" : aaSorting,
            "aoColumns" : aoColumns,
            "oLanguage" : {
                "sProcessing": "<?php echo _('Loading')?>...",
                "sLengthMenu": "Show _MENU_ entries",
                "sZeroRecords": "<?php echo _('No services found for this asset')?>",
                "sEmptyTable": "<?php echo _('No services found')?>",
                "sLoadingRecords": "<?php echo _('Loading') ?>...",
                "sInfo": "<?php echo _('Showing _START_ to _END_ of _TOTAL_ services')?>",
                "sInfoEmpty": "<?php echo _('Showing 0 to 0 of 0 entries')?>",
                "sInfoFiltered": "(<?php echo _('filtered from _MAX_ total entries')?>)",
                "sInfoPostFix": "",
                "sInfoThousands": ",",
                "sSearch": "<?php echo _('Search by service')?>",
                "sUrl": "",
                "oPaginate": {
                    "sFirst":    "<?php echo _('First') ?>",
                    "sPrevious": "<?php echo _('Previous') ?>",
                    "sNext":     "<?php echo _('Next') ?>",
                    "sLast":     "<?php echo _('Last') ?>"
                }
            },
            "fnInitComplete": function(oSettings)
            {
                __self.remove_all_filters();
            },
            "fnRowCallback" : function(nRow, aData, iDrawIndex, iDataIndex)
            {
                if (__self.edit_mode == 1)
                {
                    __create_checkbox(nRow, aData);

                    __create_icon_actions(nRow, aData);
                }
                else
                {
                    $("td:last-child", nRow).empty()

                    if (aData['DT_RowData']['nagios'] == 0)
                    {
                        var nagios_enabled = '<?php echo _('No')?>';
                    }
                    else
                    {
                        var nagios_enabled = '<?php echo _('Yes')?>';
                    }

                    $("td:last-child", nRow).html(nagios_enabled);
                }
            },
            "fnServerData": function (sSource, aoData, fnCallback, oSettings)
            {
                oSettings.jqXHR = $.ajax(
                {
                    "dataType": 'json',
                    "type": "POST",
                    "url": sSource,
                    "data": aoData,
                    "beforeSend": function(xhr)
                    {

                    },
                    "success": function (json)
                    {
                        //DataTables Stuffs
                        $(oSettings.oInstance).trigger('xhr', oSettings);
                        fnCallback(json);

                        if (__self.edit_mode == 1)
                        {
                            var num_rows = __self.get_num_total_rows();

                            //Handler for checkbox which checks all rows
                            if (num_rows == 0)
                            {
                                $('[data-bind="chk-all-rows"]', __self.dt_obj).prop('disabled', true);
                                $('[data-bind="chk-all-rows"]', __self.dt_obj).off('change');
                            }
                            else
                            {
                                $('[data-bind="chk-all-rows"]', __self.dt_obj).prop('disabled', false);
                                $('[data-bind="chk-all-rows"]', __self.dt_obj).on('change', function(){
                                    __self.check_all_manual();
                                });
                            }

                            __self.manage_row_selection();
                        }
                    },
                    "error": function(data)
                    {
                        //Check expired session
                        var session = new Session(data, '');

                        if (session.check_session_expired() == true)
                        {
                            session.redirect();
                            return;
                        }

                        //DataTables Stuffs
                        var json = $.parseJSON('{"sEcho": '+aoData[0].value+', "iTotalRecords": 0, "iTotalDisplayRecords": 0, "aaData": "" }');
                        fnCallback(json);

                        //Handler for checkbox which checks all rows
                        if (__self.edit_mode == 1)
                        {
                            $('[data-bind="chk-all-rows"]', __self.dt_obj).prop('disabled', true);
                            $('[data-bind="chk-all-rows"]', __self.dt_obj).off('change');
                        }
                    },
                    "complete": function()
                    {

                    }
                });
            }
        });
    };


    this.reload_list = function(refilter)
    {
        __self.dt_obj.fnDraw(refilter);
    };


    this.apply_to_selected_rows = function(action)
    {
        //Remove previous notifications
        remove_previous_notifications();

        var confirm_keys = { "yes" : __messages.confirm_yes, "no" : __messages.confirm_no};

        switch(action)
        {
            case 'delete':
                var confirm_msg = __messages.delete_selected;
                var callback    = __action_callbacks.delete;
                var refilter    = true;
            break;

            case 'enable_monitoring':
            case 'disable_monitoring':
                var confirm_msg = (action == 'enable_monitoring') ? __messages.monitoring_enabled : __messages.monitoring_disabled;
                var callback    = __action_callbacks.monitoring;
                var refilter    = false;
            break;

            default:
                return false;
        }

        av_confirm(confirm_msg, confirm_keys).done(function(){

            var __selected_rows        = __self.get_selection();
                __selected_rows.action = action;


            callback('service', __selected_rows).done(function(data) {

                //Check expired session
                var session = new Session(data, '');

                if (session.check_session_expired() == true)
                {
                    session.redirect();

                    return;
                }

                //Remove all selected filters
                __self.remove_all_filters();

                 //Reload list
                __self.reload_list(refilter);

                //Reset form
                __action_callbacks.reset('service');


                var __success_msg = data.data;
                notify(__success_msg, 'nf_success', true);

                window.scrollTo(0,0);

            }).fail(function(xhr) {

                //Check expired session
                var session = new Session(xhr.responseText, '');

                if (session.check_session_expired() == true)
                {
                    session.redirect();

                    return;
                }

                var __error_msg = __messages.unknown_error;

                if (typeof(xhr.responseText) != 'undefined' && xhr.responseText != '')
                {
                    __error_msg = xhr.responseText;
                }

                var __style = 'width: 70%; text-align:center; margin:0px auto;';
                show_notification(__msg_container, __error_msg, 'nf_error', 15000, true, __style);

                window.scrollTo(0,0);
            });
        });
    };


    this.delete_row = function(row_id)
    {
        //Remove previous notifications
        remove_previous_notifications();

        var confirm_keys = {"yes" : __messages.confirm_yes, "no" : __messages.confirm_no};

        av_confirm(__messages.delete_one, confirm_keys).done(function(){

            var r_data = __self.get_row_data(row_id);

            var __selected_row = {
                'asset_id'         : __asset_data.id,
                'controllers'      : __controllers,
                'selection_type'   : 'manual',
                'selection_filter' : '',
                'items'            : new Array(r_data['row_data'])
            };


            __action_callbacks.delete('service', __selected_row).done(function(data) {

                //Check expired session
                var session = new Session(data, '');

                if (session.check_session_expired() == true)
                {
                    session.redirect();

                    return;
                }

                //Delete row from list
                var n_tr     = $('#' + row_id);
                __self.dt_obj.fnDeleteRow(n_tr[0], null, true);

                //Reset form
                __action_callbacks.reset('service');


                var __success_msg = data.data;
                notify(__success_msg, 'nf_success', true);

                window.scrollTo(0,0);

            }).fail(function(xhr) {

                //Check expired session
                var session = new Session(xhr.responseText, '');

                if (session.check_session_expired() == true)
                {
                    session.redirect();

                    return;
                }

                var __error_msg = __messages.unknown_error;

                if (typeof(xhr.responseText) != 'undefined' && xhr.responseText != '')
                {
                    __error_msg = xhr.responseText;
                }


                var __style = 'width: 70%; text-align:center; margin:0px auto;';
                show_notification(__msg_container, __error_msg, 'nf_error', 15000, true, __style);

                window.scrollTo(0,0);
            });
        });
    };


    this.add_row = function(row_data)
    {
        if (typeof(row_data) == 'undefined' || typeof(row_data) == null)
        {
            return false;
        }

        __self.dt_obj.fnAddData(row_data, true);
    };


    this.update_row = function(row_id, row_data)
    {
        if (typeof(row_data) == 'undefined' || typeof(row_data) == null)
        {
            return false;
        }

        var __n_tr = $('#' + row_id);

        __self.dt_obj.fnUpdate(row_data, __n_tr[0], null, false, false);

        //Update Row ID
        $('#' + row_id).attr('id', row_data['DT_RowId']);

        //Create checkbox and icon actions
        __create_checkbox(__n_tr[0], row_data);

        __create_icon_actions(__n_tr[0], row_data);
    };


    this.update_button_status = function()
    {
        var num_selected_rows = __self.get_num_selected_rows();

        if (num_selected_rows == 0)
        {
            //Button to delete all rows
            $('#' + __prefix + '_delete_selection').addClass('disabled');
            $('#' + __prefix + '_delete_selection').off('click');


            $('[data-bind="' + __prefix + '_m-actions"]').dropdown('disable');
            $('[data-bind="' + __prefix + '_m-actions"]').addClass('av_b_disabled');
            $('[data-bind="' + __prefix + '_m-actions"]').off('click', 'a');

            //Link to check all rows
            $('[data-bind="' + __prefix + '_chk-all-filter"]').off('click');
        }
        else
        {
            //Button to delete all rows
            $('#' + __prefix + '_delete_selection').removeClass('disabled');

            $('#' + __prefix + '_delete_selection').off('click');
            $('#' + __prefix + '_delete_selection').on('click', function(){
                __self.apply_to_selected_rows('delete');
            });

            $('[data-bind="' + __prefix + '_m-actions"]').dropdown('enable');
            $('[data-bind="' + __prefix + '_m-actions"]').removeClass('av_b_disabled');

            var drop_down_actions = $('[data-bind="' + __prefix + '_m-actions"]').attr('data-dropdown');

            $(drop_down_actions).off('click').on('click', 'a', function(){

                var __m_action = $(this).attr('data-bind');

                if (__m_action != '')
                {
                    __self.apply_to_selected_rows(__m_action);
                }
            });

            //Link to check all rows
            $('[data-bind="' + __prefix + '_chk-all-filter"]').off('click');
            $('[data-bind="' + __prefix + '_chk-all-filter"]').on('click', function(){
                __self.check_all_filter();
            });
        }
    };


    /* Function to unmark all the filters */
    this.remove_all_filters = function()
    {
        __self.db.clean_checked();

        __selection.type   = 'manual';
        __selection.filter = '';

        $('[data-bind="chk-all-rows"]', __self.dt_obj).prop('checked', false);
        $('.rs_check').prop('checked', false);
    };



    /**************************************************************************/
    /*************************  SELECTION FUNCTIONS  **************************/
    /**************************************************************************/


    this.manage_check_selection = function(input)
    {
        if($(input).prop('checked'))
        {
            __self.db.save_check($(input).val());
        }
        else
        {
            __self.db.remove_check($(input).val());
        }

        __selection.type   = 'manual';
        __selection.filter = '';

        __self.manage_row_selection();
    };


    this.manage_row_selection = function()
    {
        var c_all   = $('.rs_check').length;
        var c_check = $('.rs_check:checked').length;
        var r_total = __self.get_num_total_rows();

        $('[data-bind="chk-all-rows"]', __self.dt_obj).prop('checked', (c_all > 0 && c_all == c_check));

        if (__selection.type == 'manual' && c_all > 0 && c_all == c_check && r_total > c_all)
        {
            var elem = $('[data-bind="' + __prefix + '_msg-selection"]');

            var text1 = __messages.selected_rows.replace('###', c_all);
            var text2 = __messages.select_all_rows.replace('###', r_total);

            $('span', elem).text(text1);
            $('a', elem).text(text2);

            elem.show();
        }
        else
        {
            $('[data-bind="' + __prefix + '_msg-selection"]').hide();
        }

        __self.update_button_status();
    };


    this.check_all_manual = function()
    {
        var status = $('[data-bind="chk-all-rows"]', __self.dt_obj).prop('checked');

        $('.rs_check').prop('checked', status).trigger('change');

        __selection.type   = 'manual';
        __selection.filter = '';
    };


    this.check_all_filter = function()
    {
        $('[data-bind="' + __prefix + '_msg-selection"]').hide();

        __selection.type   = 'filter';
        __selection.filter =  __self.get_current_filter();
    };


    this.get_num_total_rows = function()
    {
        try
        {
            return __self.dt_obj.fnSettings()._iRecordsTotal;
        }
        catch(err)
        {
            return 0;
        }
    };


    this.get_current_filter = function()
    {
        try
        {
            return __self.dt_obj.fnSettings().oPreviousSearch.sSearch;
        }
        catch(err)
        {
            return '';
        }
    };


    this.get_num_selected_rows = function()
    {
        if (__selection.type == 'filter')
        {
            return __self.get_num_total_rows();
        }
        else
        {
            return $('.rs_check:checked').length;
        }
    };


    this.get_row_data = function(row_id)
    {
        var r_data = {};

        try
        {
            var __n_tr   = $('#' + row_id);
            var __r_data = __self.dt_obj.fnGetData(__n_tr[0]);

            r_data = {
                'row_id'   : __r_data['DT_RowId'],
                'row_data' : __r_data['DT_RowData']
            };
        }
        catch(err)
        {
            ;
        }

        return r_data;
    };


    this.get_selection = function()
    {
        var sel_r_config = {};

        var c_check  = $('.rs_check:checked').length;
        var r_total  = __self.get_num_total_rows();
        var c_filter = __self.get_current_filter();

        var cnd_1 = (c_check == r_total && typeof(c_filter) != 'undefined' && c_filter == '');
        var cnd_2 = (__selection.type == 'filter');

        if (cnd_1 || cnd_2)
        {
            sel_r_config = {
                'asset_id'         : __asset_data.id,
                'controllers'      : __controllers,
                'selection_type'   : 'filter',
                'selection_filter' : __self.get_current_filter()
            };
        }
        else
        {
            var rows = [];

            $('.rs_check:checked').each(function(index, elem)
            {
                var __row_id = $(elem).val();
                var __r_data = __self.get_row_data(__row_id);

                rows.push(__r_data['row_data']);
            })

            sel_r_config = {
                'asset_id'         : __asset_data.id,
                'controllers'      : __controllers,
                'selection_type'   : 'manual',
                'selection_filter' : '',
                'items'            : rows
            };
        }

        return sel_r_config;
    };


    /**************************************************************************/
    /****************************  HELPER FUNCTIONS  **************************/
    /**************************************************************************/

    function __get_dt_parameters()
    {
        var sort = [[1, "asc"]];

        var columns =  [
            {"bSortable": false, "sClass" : "th_asset"},
            {"bSortable": true,  "sClass" : "th_s_port"},
            {"bSortable": true,  "sClass" : "th_s_protocol"},
            {"bSortable": true,  "sClass" : "th_s_name"},
            {"bSortable": true,  "sClass" : "th_s_status"},
            {"bSortable": false, "sClass" : "center th_m_actions"}
        ];

        if (__self.edit_mode == 1)
        {
            columns.unshift({"bSortable": false, "sClass": "center", "bVisible" : true});
            columns.push({"bSortable": false, "sClass": "center th_actions", "bVisible" : true});
        }
        else
        {
            columns.unshift({"bSortable": false, "sClass": "center", "bVisible" : false});
            columns.push({"bSortable": false, "sClass": "center th_actions", "bVisible" : false});
        }

        var server_params = [
            {"name": "asset_id",  "value" : __asset_data.id},
            {"name": "asset_type","value" : __asset_data.asset_type}
        ];


        var dt_parameters = {
            'sort'          : sort,
            'columns'       : columns,
            'server_params' : server_params
        }

        return dt_parameters;
    }


    function __create_checkbox(nRow, aData)
    {
        $("td:first-child", nRow).empty();

        var input = $('<input>',
        {
            'type'   : 'checkbox',
            'value'  : aData['DT_RowId'] ,
            'class'  : 'rs_check',
            'change' : function()
            {
                __self.manage_check_selection(this);
            },
            'click'  : function(e)
            {
                //To avoid to open the tray bar when clicking on the checkbox.
                e.stopPropagation();
            }
        }).appendTo($("td:first-child", nRow))

        if (__self.db.is_checked(aData['DT_RowId']) || __selection.type == 'filter')
        {
            input.prop('checked', true);
        }
    }


    function __create_icon_actions(nRow, aData)
    {
        //Replace Nagios data
        $("td:nth-last-child(2)", nRow).empty();

        var nagios_enabled = (aData['DT_RowData']['nagios'] == 0) ? false : true;

        var div = $('<div>',
        {
            'class' : 'rs_sw_monitoring toggle-modern',
            'id'    : aData['DT_RowId']
        }).appendTo($("td:nth-last-child(2)", nRow));

        $('.rs_sw_monitoring', nRow).toggles({
            "text" : {
                "on"  : '<?php echo _('Yes')?>',
                "off" : '<?php echo _('No')?>'
            },
            "on" : nagios_enabled,
            "width" : 50, // width used if not set in css
            "height" : 18, // height if not set in css
        });

        $('.rs_sw_monitoring', nRow).on('toggle', function (e, nagios_enabled) {

            var __r_data = __self.get_row_data($(this).attr('id'));
            var __action = (nagios_enabled == true) ? 'enable_monitoring' : 'disable_monitoring';

            var __s_row_config = {
                'asset_id'         : __asset_data.id,
                'controllers'      : __controllers,
                'action'           : __action,
                'selection_type'   : 'manual',
                'selection_filter' : '',
                'items'            : new Array(__r_data['row_data'])
            };

            __action_callbacks.monitoring('service', __s_row_config);
        });


        $("td:last-child", nRow).empty();

        //Edit row (Service)
        $('<img></img>',
        {
            "class" : "img_action",
            "src"   : "/ossim/pixmaps/edit.png",
            'click'  : function(e)
            {
                e.stopPropagation();

                var r_data = __self.get_row_data(aData['DT_RowId']);

                __action_callbacks.edit('service', r_data);
            }
        }).appendTo($("td:last-child", nRow));


        //Delete row (Service)
        $('<img></img>',
        {
            "class" : "img_action",
            "src"   : "/ossim/pixmaps/delete-big.png",
            'click'  : function(e)
            {
                e.stopPropagation();

                __self.delete_row(aData['DT_RowId']);
            }
        }).appendTo($("td:last-child", nRow));
    }
};
