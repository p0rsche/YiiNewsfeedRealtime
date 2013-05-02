/**
 * csocket file
 *
 * @author Vladimir Gerasimov <freelancervip@gmail.com>
 *
 */
//some @TODO 's:
// don't let socket to receive messages if 'auth' was not successfull (even if auth was not successfull by server response and socket should be disconnected)
// grab recent activity count from yii settings
//не будем гадить в global
(function(){

    /**
     * Pushes html into widget
     *
     * @param html Prepared html string
     */
    function push(html, history_count) {
        $('.newsfeed .header').after(html);
        //get all li elements except header, slidedown new one and remove the last one from DOM tree
        var uls = $('.newsfeed ul li').not('.header'),
            count = uls.length;
        if(count > 0){
            uls.first().slideDown(function(){
                //@TODO grab this setting from yii config
                if(count > history_count){
                    for(var i=history_count; i<count;i++){
                        $(uls[i]).remove(); //removing useless elements from DOM
                    }
                }
            });
        }
    }
    /**
     * Routes received message to right destination and runs push
     *
     * @param event Message object
     *
     * @see push
     */
    function routeMessage(channel, event, server_time, history_count) {
        var revent;
        try {
            revent = $.parseJSON(event);
        }
        catch (e){
            console.log(e);
            return;
        }
        if (!channel)
            return;
        if (channel == 'newsfeed') {
            var username, userpicurl, profileurl, activity, what, whaturl, time, timediff, html;
            if (revent.event == 'vote') {
                username = revent.eventData.voter.name;
                userpicurl = revent.eventData.voter.userpic;
                what = revent.eventData.product.title || 'something';
                whaturl = '/product/' + revent.eventData.product.id;
            }
            else if (revent.event == 'follow') {
                username = revent.eventData.follower.name;
                userpicurl = revent.eventData.follower.userpic;
            }
            else if (revent.event == 'comment') {
                username = revent.eventData.commentator.name;
                userpicurl = revent.eventData.commentator.userpic;
                what = revent.eventData.product.title || 'something';
                whaturl = '/product/' + revent.eventData.product.id;
            }
            else {
                return;
            }
            profileurl = '/' + username;
            activity = revent.eventData.type;
            time = revent.eventData.timestamp;
            timediff = timeDiff(time, server_time);
            html = generateHtml(username, userpicurl, profileurl, activity, what, whaturl, timediff);
            push(html, history_count);
        }
    }
    /**
     * Prints time in nicier format
     */
    function hTime() {
        return ((new Date()).toUTCString());
    }
    /**
     * Generates html for newsfeed
     *
     * @param username Name of the user
     * @param userpicurl Image link
     * @param profileurl Link to user's profile
     * @param activity Activity text
     */
    function generateHtml(username, userpicurl, profileurl, activity, what, whaturl, time) {
        var html,
            friendlyactivity;
        if (activity == 'want') {
            friendlyactivity = 'wants <a href="' + whaturl + '">' + what + '</a>';
        }
        else if (activity == 'has') {
            friendlyactivity = 'has <a href="' + whaturl + '">' + what + '</a>';
        }
        else if (activity == 'commented') {
            friendlyactivity = 'commented on <a href="' + whaturl + '">' + what + '</a>';
        }
        else if (activity == 'alsocommented') {
            friendlyactivity = 'also commented on <a href="' + whaturl + '">' + what + '</a>';
        }
        else if (activity == 'follow') {
            friendlyactivity = 'is now following you';
        }
        else {
            //unknown activity, just return
            return;
        }

        html = '<li style="display:none">';
        html += '<div class="userpic"><a href="' + profileurl + '"><img src="' + userpicurl + '" /></a></div>';
        html += '<div class="time">' + time + '</div>';
        html += '<div class="activity"><a href="' + profileurl + '">' + username + '</a> ' + friendlyactivity + '</div>';
        html += '</li>';

        return html;
    };
    /**
     * Calculates time difference between 'time' and now.
     *
     * @param time From time
     */
    function timeDiff(time, server_time_now) {
        /**
         * Corrects ending for english words
         *
         * @param num Number
         * @param word The word to which is added to the end
         *
         * @return string Word with correct ending
         */
        var getEnd = function (num, word) {
            if (!word)
                word = '';
            num = num % 100;
            if (num == 11) return word += 's';
            num = num % 10;
            if (num == 1) return word;

            return word += 's';
        }
        var now = server_time_now,
            from = time,
            diff = now - from; //time in seconds
        days = Math.floor(diff / 86400);
        if (days > 0)
            return (days + ' ' + getEnd(days, 'day') + ' ago');
        remained_secs = diff - days * 86400;
        hours = Math.floor(remained_secs / 3600);
        if (hours > 0)
            return (hours + ' ' + getEnd(hours, 'hour') + ' ago');
        remained_secs2 = remained_secs - hours * 3600;
        minutes = Math.floor(remained_secs2 / 60);
        if (minutes > 0)
            return (minutes + ' ' + getEnd(minutes, 'minute') + ' ago');

        return 'moment ago';
    };
    /**
     * CSocket constructor
     * @param config Array configuration
     */
    function CSocket(config, hash) {
        var defaultConfig = {
            host: 'http://localhost',
            port: 2206,
            channels: ['newsfeed'],
            history_count: 5,
            uid: 0,
            hash: hash,
            debug: true
        };

        this.opts = $.extend({}, defaultConfig, config);//merging configs
    };
    /**
     * Runs socket io
     */
    CSocket.prototype.run = function () {

        var server = this.opts.host + ':' + this.opts.port,
            socket = io.connect(server, {
                /*'try multiple transports':   true*/
                /*
                 'secure':                    false,
                 'connect timeout':           5000,
                 'try multiple transports':   true,
                 'reconnect':                 true,
                 'reconnection delay':        1000,
                 'reopen delay':              3000,
                 'max reconnection attempts': 10,
                 'sync disconnect on unload': true,
                 'auto connect':              false,
                 'remember transport':        false,
                 'transports': [
                 'websocket'
                 , 'flashsocket'
                 , 'htmlfile'
                 , 'xhr-multipart'
                 ]*/
            }),
            uid = this.opts.uid,
            channels = this.opts.channels,
            history_count = this.opts.history_count,
            hash = this.opts.hash,
            debug = this.opts.debug;
        /**
         * When connection established
         */
        socket.on('connect', function () {
            (debug)? console.log('Connected to server at ' + hTime()) : '';
            /**
             * Authentication phase
             */
            socket.emit('auth', {uid:uid, hash: hash}, function (response) {
                if (response == 'ok') {
                    (debug)? console.log('Auth success at ' + hTime()) : '';
                    socket.emit('subscribe', {channels:channels}, function (response) {
                        if (response == 'ok') {
                            (debug)? console.log('Subscribed successfully at ' + hTime() + ' for channels ' + channels.join(', ')) : '';
                        }
                        else {
                            console.log('Crazy server said: ' + response + ' at ' + hTime());
                        }
                    });
                }
                else if (response == 'err') {
                    console.log('Auth failed at ' + hTime());
                }
                else {
                    console.log('Crazy server said: ' + response + ' at ' + hTime());
                }
            });
        });
        /**
         * Loads last activity into widget
         */
        socket.on('lastactivity', function (data) {
            //do work only if receive array of last activity messages
            if ((data) && ('message' in data) && (typeof data.message === 'object') && (data.message instanceof Array)) {
                var i = (data.message).length;
                while(i > 0) {
                    routeMessage('newsfeed', data.message[i-1], data.server_time, history_count);
                    i--;
                }
            }
        });
        /**
         * When socket receives a message
         */
        socket.on('msg', function (data) {
            //magic starts here...
            ch = data.channel.split(':');
            channel = ch[ch.length-1];
            routeMessage(channel, data.message, data.server_time, history_count);
        });
        /**
         * When disconnect
         */
        socket.on('disconnect', function () {
            /*setTimeout(function () {
             (debug)? console.log('Disconnected from server at ' + hTime()) : '';
             }, 500);*/
        });
        /**
         * When reconnects
         */
        socket.on('reconnect', function () {
            (debug)? console.log('Reconnecting at ' + hTime()) : '';
        });
        /**
         * Any socket errors handles here
         */
        socket.on('error', function (err) {
            console.log('Crazy server sent an error: ' + err + ' at ' + hTime());
        });
    };
})();