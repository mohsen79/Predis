var server = require('http').Server();
var io = require('socket.io')(server);
const Redis = require('ioredis');
var redis = new Redis();
redis.subscribe('test-channel');
redis.on('message', function(channel, message) {
    console.log('connected');
    message = JSON.parse(message);
    console.log(message.data.name);
});
server.listen(3000);