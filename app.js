var connect = require('connect');

var app = connect()
  .use(connect.logger('short'))
  .use(connect.static('htdocs'))
  .use(function(req, res){
    res.end('hello world\n');
  })
.listen(process.env.PORT || 3000);
 