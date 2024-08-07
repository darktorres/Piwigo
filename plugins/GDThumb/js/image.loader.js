function ImageLoader(opts) {
  this.opts = Object.assign(
    {
      maxRequests: 6,
      onChanged: function () {}, // No-op function
    },
    opts || {}
  );

  this.loaded = 0;
  this.errors = 0;
  this.errorEma = 0;
  this.paused = false;
  this.current = [];
  this.queue = [];
  this.pool = [];
}

ImageLoader.prototype = {
  remaining: function () {
    return this.current.length + this.queue.length;
  },

  add: function (urls) {
    this.queue = this.queue.concat(urls);
    this._fireChanged("add");
    this._checkQueue();
  },

  clear: function () {
    this.queue.length = 0;
    while (this.current.length) {
      const img = this.current.pop();
      img.onload = null;
      img.onerror = null;
      img.onabort = null;
    }
    this.loaded = this.errors = this.errorEma = 0;
  },

  pause: function (val) {
    if (val !== undefined) {
      this.paused = val;
      this._checkQueue();
    }
    return this.paused;
  },

  _checkQueue: function () {
    while (!this.paused && this.queue.length && this.current.length < this.opts.maxRequests) {
      this._processOne(this.queue.shift());
    }
  },

  _processOne: function (url) {
    const img = this.pool.shift() || new Image();
    this.current.push(img);
    const that = this;

    function eventHandler(e) {
      img.onload = null;
      img.onerror = null;
      img.onabort = null;
      that.current.splice(that.current.indexOf(img), 1);

      if (e.type === "load") {
        that.loaded++;
        that.errorEma *= 0.9;
      } else {
        that.errors++;
        that.errorEma++;
        if (that.errorEma >= 20 && that.errorEma < 21) that.paused = true;
      }

      that._fireChanged(e.type, img);
      that._checkQueue();
      that.pool.push(img);
    }

    img.onload = eventHandler;
    img.onerror = eventHandler;
    img.onabort = eventHandler;
    img.src = url;
  },

  _fireChanged: function (type, img) {
    this.opts.onChanged(type, img);
  },
};
