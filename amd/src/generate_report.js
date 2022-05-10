define(['jquery',
        'core/ajax',
        'core/notification'], function($, Ajax, Notification) {
    "use strict";

    var GenerateReport = function(requestorId, userId, userName, contextId) {
        this.requestorId = parseInt(requestorId);
        this.userId = parseInt(userId);
        this.userName = userName;
        this.start = null;
        this.end = null;
        this.contextId = contextId;
        this.timer = 2500;
        this.file = false;
        this.formdata = {};
        this.polling = null;

        $('#submitdate').click(function(e) {
            e.preventDefault();

            var startDate = $('#startInput').val();
            var endDate = $('#endInput').val();
            var completion = this.checkCompletion(startDate, endDate);
            if (!completion) {
                return Notification.alert("Erreur", "Saisir les dates de début et de fin de période.");
            }

            var formdata = {
                requestorid: this.requestorId,
                userid: this.userId,
                username: this.userName,
                start: $('#startInput').val(),
                end: $('#endInput').val(),
                contextid: this.contextId
            };
            this.formdata = formdata;

            Ajax.call([{
                methodname: 'tool_time_report_generate_time_report',
                args: { jsonformdata: JSON.stringify(formdata) },
                done: function() {
                    $('#report-area').addClass('alert-warning')
                        .html('<div class="spinner-border text-warning" role="status"></div> Chargement du rapport...');
                    var that = this;
                    (function foo() {
                        that.polling = setInterval(pollFile.bind(that), 7500);
                    })();
                }.bind(this),
                fail: Notification.exception
            }]);
        }.bind(this));
    };

    GenerateReport.prototype.checkCompletion = function(startDate, endDate) {
        if (startDate.length == 0||endDate.length == 0) {
            return false;
        }
        return true;
    };

    var pollFile = function() {
        Ajax.call([{
            methodname: 'tool_time_report_poll_report_file',
            args: { jsonformdata: JSON.stringify(this.formdata) },
            done: function(data) {
                if (data.status == true) {
                    $('#report-area').removeClass('alert-warning').addClass('alert-success')
                        .html('<a href="'+data.path+'" target="_blank"><i class="fa fa-download"></i> Télécharger le rapport</a>');
                    clearInterval(this.polling);
                    return;
                }
            }.bind(this),
            fail: Notification.exception
        }]);
    };

    return {
        generateReport: function(requestorId, userId, userName, contextId) {
            return new GenerateReport(requestorId, userId, userName, contextId);
        }
    };
});