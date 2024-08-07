class ImageLoader {
  constructor(opts = {}) {
    const defaultOptions = {
      maxRequests: 6,
      onChanged: () => {}, // No-op function
    };
    this.opts = { ...defaultOptions, ...opts };

    this.loaded = 0;
    this.errors = 0;
    this.errorEma = 0;
    this.paused = false;
    this.current = [];
    this.queue = [];
    this.pool = [];
  }

  remaining() {
    return this.current.length + this.queue.length;
  }

  add(urls) {
    this.queue.push(...urls);
    this._fireChanged("add");
    this._checkQueue();
  }

  clear() {
    this.queue = [];
    this.current.forEach((img) => this._removeEventListeners(img));
    this.current = [];
    this.loaded = 0;
    this.errors = 0;
    this.errorEma = 0;
  }

  pause(val) {
    if (val !== undefined) {
      this.paused = val;
      this._checkQueue();
    }
    return this.paused;
  }

  _checkQueue() {
    while (!this.paused && this.queue.length > 0 && this.current.length < this.opts.maxRequests) {
      this._processOne(this.queue.shift());
    }
  }

  _processOne(url) {
    const img = this.pool.pop() || new Image();
    this.current.push(img);

    const eventHandler = this._handleEvent.bind(this, img);
    img.onload = eventHandler;
    img.onerror = eventHandler;
    img.onabort = eventHandler;
    img.src = url;
  }

  _handleEvent(img, event) {
    this._removeEventListeners(img);
    this.current.splice(this.current.indexOf(img), 1);

    if (event.type === "load") {
      this.loaded++;
      this.errorEma *= 0.9;
    } else {
      this.errors++;
      this.errorEma++;
      if (this.errorEma >= 20 && this.errorEma < 21) {
        this.paused = true;
      }
    }

    this._fireChanged(event.type, img);
    this._checkQueue();
    this.pool.push(img);
  }

  _removeEventListeners(img) {
    img.onload = null;
    img.onerror = null;
    img.onabort = null;
  }

  _fireChanged(type, img) {
    if (typeof this.opts.onChanged === "function") {
      this.opts.onChanged(type, img);
    }
  }
}
