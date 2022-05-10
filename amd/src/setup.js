define(['core/str'], function(Str) {
    "use strict";

    var componentName = 'tool_time_report';
    
    return {
        getPresets: function() {
            return {
                prevText: '<',
                nextText: '>',
                monthNames: [],
                monthNamesShort: [],
                dayNames: [],
                dayNamesShort: [],
                dayNamesMin: [],
                currentText: '',
                closeText: '',
                weekHeader: '',
                dateFormat: 'mmyy',
                firstDay: 1,
                changeMonth: true,
                changeYear: true,
                isRTL: false,
                showMonthAfterYear: false,
                startDate: '01/01/2017'
            };
        },

        init: function() {
            var stringsPromise = Str.get_strings([
                { key: 'datepicker:january', component: componentName },
                { key: 'datepicker:february', component: componentName },
                { key: 'datepicker:march', component: componentName },
                { key: 'datepicker:april', component: componentName },
                { key: 'datepicker:may', component: componentName },
                { key: 'datepicker:june', component: componentName },
                { key: 'datepicker:july', component: componentName },
                { key: 'datepicker:august', component: componentName },
                { key: 'datepicker:september', component: componentName },
                { key: 'datepicker:october', component: componentName },
                { key: 'datepicker:november', component: componentName },
                { key: 'datepicker:december', component: componentName },
                { key: 'datepicker:sunday', component: componentName },
                { key: 'datepicker:monday', component: componentName },
                { key: 'datepicker:tuesday', component: componentName },
                { key: 'datepicker:wednesday', component: componentName },
                { key: 'datepicker:thursday', component: componentName },
                { key: 'datepicker:friday', component: componentName },
                { key: 'datepicker:saturday', component: componentName },
                { key: 'datepicker:weekheader', component: componentName },
                { key: 'today', component: 'core'},
                { key: 'datepicker:close', component: componentName }
            ]);

            return stringsPromise;
        },

        setStrings: function(strings) {
            var monthsStrings = strings.slice(0, 12),
                daysStrings = strings.slice(12, 19),
                week = strings[19],
                today = strings[20],
                close = strings[21],
                shortMonthNames = [],
                shortDayNames = [];

            for (var i = 0; i < monthsStrings.length; i++) {
                shortMonthNames[i] = monthsStrings[i].substring(0, 3);
            }

            for (var i = 0; i < daysStrings.length; i++) {
                shortDayNames[i] = daysStrings[i].substring(0, 3);
            }

            return {
                prevText: '<',
                nextText: '>',
                monthNames: monthsStrings,
                monthNamesShort: shortMonthNames,
                dayNames: daysStrings,
                dayNamesShort: shortDayNames,
                dayNamesMin: shortDayNames,
                currentText: today,
                closeText: close,
                weekHeader: week,
                dateFormat: 'mmyy',
                firstDay: 1,
                changeMonth: true,
                changeYear: true,
                isRTL: false,
                showMonthAfterYear: false,
                startDate: '01/01/2017'
            };
        }
    };
});