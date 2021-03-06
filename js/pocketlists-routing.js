(function ($) {
    'use strict';

    $.storage = new $.store();
    $.pocketlists_routing = {
        options: {
            user_id: 0
        },
        init: function (options) {
            var that = this;
            that.options = options;
            if (typeof($.History) != "undefined") {
                $.History.bind(function () {
                    that.dispatch();
                });
            }

            var hash = window.location.hash;
            if (hash === '#/' || !hash) {
                hash = $.storage.get('/pocketlists/hash/' + that.options.user_id);
                if (hash && hash != null) {
                    $.wa.setHash('#/' + hash);
                } else {
                    this.dispatch();
                }
            } else {
                $.wa.setHash(hash);
            }
        },
        // dispatch() ignores the call if prevHash == hash
        prevHash: null,
        hash: null,
        /** Current hash. No URI decoding is performed. */
        getHash: function () {
            return this.cleanHash();
        },
        /** Make sure hash has a # in the begining and exactly one / at the end.
         * For empty hashes (including #, #/, #// etc.) return an empty string.
         * Otherwise, return the cleaned hash.
         * When hash is not specified, current hash is used. No URI decoding is performed. */
        cleanHash: function (hash) {
            if (typeof hash == 'undefined') {
                // cross-browser way to get current hash as is, with no URI decoding
                hash = window.location.toString().split('#')[1] || '';
            }

            if (!hash) {
                return '';
            } else if (!hash.length) {
                hash = '' + hash;
            }
            while (hash.length > 0 && hash[hash.length - 1] === '/') {
                hash = hash.substr(0, hash.length - 1);
            }
            hash += '/';

            if (hash[0] != '#') {
                if (hash[0] != '/') {
                    hash = '/' + hash;
                }
                hash = '#' + hash;
            } else if (hash[1] && hash[1] != '/') {
                hash = '#/' + hash.substr(1);
            }

            if (hash == '#/') {
                return '';
            }

            return hash;
        },
        // if this is > 0 then this.dispatch() decrements it and ignores a call
        skipDispatch: 0,
        /** Cancel the next n automatic dispatches when window.location.hash changes */
        stopDispatch: function (n) {
            this.skipDispatch = n;
        },
        /** Implements #hash-based navigation. Called every time location.hash changes. */
        dispatch: function (hash) {
            if (this.skipDispatch > 0) {
                this.skipDispatch--;
                return false;
            }
            if (hash === undefined || hash === null) {
                hash = window.location.hash;
            }
            hash = hash.replace(/(^[^#]*#\/*|\/$)/g, '');
            /* fix syntax highlight*/
            if (this.hash !== null) {
                this.prevHash = this.hash;
            }
            this.hash = hash;
            if (hash) {
                hash = hash.split('/');
                if (hash[0]) {
                    var actionName = "";
                    var attrMarker = hash.length;
                    var lastValidActionName = null;
                    var lastValidAttrMarker = null;
                    for (var i = 0; i < hash.length; i++) {
                        var h = hash[i];
                        if (i < 2) {
                            if (i === 0) {
                                actionName = h;
                            } else if (parseInt(h, 10) != h && h.indexOf('=') == -1) {
                                actionName += h.substr(0, 1).toUpperCase() + h.substr(1);

                            } else {
                                break;
                            }
                            if (typeof(this[actionName + 'Action']) == 'function') {
                                lastValidActionName = actionName;
                                lastValidAttrMarker = i + 1;
                            }
                        } else {
                            break;
                        }
                    }
                    attrMarker = i;

                    if (typeof(this[actionName + 'Action']) !== 'function' && lastValidActionName) {
                        actionName = lastValidActionName;
                        attrMarker = lastValidAttrMarker;
                    }

                    var attr = hash.slice(attrMarker);
                    this.preExecute(actionName);
                    if (typeof(this[actionName + 'Action']) == 'function') {
                        console.info('dispatch', [actionName + 'Action', attr]);
                        this[actionName + 'Action'].apply(this, attr);
                        $.storage.set('/pocketlists/hash/' + this.options.user_id, hash.join('/'));
                    } else {
                        console.info('Invalid action name:', actionName + 'Action');
                    }
                    this.postExecute(actionName);
                } else {
                    this.preExecute();
                    this.defaultAction();
                    this.postExecute();
                }
            } else {
                this.preExecute();
                this.defaultAction();
                this.postExecute();
            }
        },
        redispatch: function () {
            this.prevHash = null;
            this.dispatch();
        },
        preExecute: function () {
            $(window).off('scroll.pl2');
        },
        postExecute: function () {
            $.pocketlists.reloadSidebar();
            this.heartbeat();
        },

        heartbeat: function () {
            $.post('?module=json&action=heartbeat');
        },

        // actions
        defaultAction: function () {
            this.todoAction();
            // this.pocketAction();
        },
        pocketAction: function (id) {
            //var self = this;
            var list_id = decodeURIComponent(this.getHash().substr(('#/pocket/' + id + '/list/').length).replace('/', '')) || 0;
            //var load_list = this.getHash().indexOf('list') > 0;
            if (list_id) {
                if (list_id === 'new') {
                    list_id = -1;
                }
            }
            var id = id || 0;
            var $list_name = $('#pl-list-name');
            if ($list_name.length) {
                $list_name.after('<i class="icon16 loading">');
            }
            // todo: load list separetly
            this.load('?module=pocket&id=' + id + '&list_id=' + list_id, function (result) {
                // show pockets
                $('#content').html(result);
                // and load selected list
                //load_list && self.listAction(list_id);
            });
        },
        listsAction: function () {
            this.pocketAction();
        },
        listAction: function (id) {
            this.load('?module=list&id=' + id, function (result) {
                $('#pl-list-content').html(result);
            });
        },
        archiveAction: function (id) {
            id = id || 0;
            this.load('?module=archive&id=' + id, function (result) {
                $('#content').html(result);
            });
        },
        logbookAction: function () {
            this.load('?module=logbook', function (result) {
                $('#content').html(result);
            });
        },
        settingsAction: function () {
            this.load('?module=settings', function (result) {
                $('#content').html(result);
            });
        },
        debugAction: function () {
            this.load('?module=debug', function (result) {
                $('#content').html(result);
            });
        },
        todoAction: function () {
            this.load('?module=todo', function (result) {
                $('#content').html(result);
            });
        },
        activityAction: function () {
            this.load('?module=activity', function (result) {
                $('#content').html(result);
            });
        },
        commentsAction: function () {
            this.load('?module=comments', function (result) {
                $('#content').html(result);
            });
        },
        favoritesAction: function () {
            this.load('?module=favorites', function (result) {
                $('#content').html(result);
            });
        },
        teamAction: function (teammate) {
            teammate = teammate || 0;
            this.load('?module=team&teammate=' + teammate, function (result) {
                $('#content').html(result);
            });
        },
        appAction: function (app) {
            app = app || 0;
            this.load('?module=app&app=' + app, function (result) {
                $('#content').html(result);
            });
        },
        /** Helper to load data into main content area. */
        load: function (url, options, fn) {
            if (typeof options === 'function') {
                fn = options;
                options = {};
            } else {
                options = options || {};
            }
            var r = Math.random();
            this.random = r;
            var self = this;
            return $.get(url, function (result, textStatus, jqXHR) {
                if ((typeof options.check === 'undefined' || options.check) && self.random != r) {
                    // too late: user clicked something else.
                    return;
                }
                if (typeof fn === 'function') {
                    fn.call(this, result);
                }
            });
        }
    }
}(jQuery));
