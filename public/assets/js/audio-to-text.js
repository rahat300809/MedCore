/**
 * MedCore AudioToText client
 * MediaRecorder → /api/audio-transcribe.php (Whisper) with Web Speech fallback.
 */
(function (global) {
  function createAudioToText(options) {
    const opts = Object.assign({
      endpoint: '/api/audio-transcribe.php',
      mode: 'clinical',
      language: '',
      chunkMs: 6000,
      onPartial: null,
      onFinal: null,
      onError: null,
      onStatus: null,
    }, options || {});

    let mediaRecorder = null;
    let mediaStream = null;
    let recognition = null;
    let active = false;
    let chunks = [];

    function status(msg) {
      if (typeof opts.onStatus === 'function') opts.onStatus(msg);
    }

    async function uploadBlob(blob) {
      if (!blob || blob.size < 800) return;
      const fd = new FormData();
      fd.append('audio', blob, 'chunk.webm');
      fd.append('mode', opts.mode);
      fd.append('refine', '1');
      if (opts.language) fd.append('language', opts.language);

      try {
        const res = await fetch(opts.endpoint, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await res.json();
        if (data.success && data.text) {
          if (typeof opts.onFinal === 'function') opts.onFinal(data.text, data);
        } else if (data.message && typeof opts.onError === 'function') {
          opts.onError(data.message);
        }
      } catch (err) {
        if (typeof opts.onError === 'function') opts.onError(String(err));
      }
    }

    async function startWhisper() {
      mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const mime = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
        ? 'audio/webm;codecs=opus'
        : 'audio/webm';
      mediaRecorder = new MediaRecorder(mediaStream, { mimeType: mime });
      chunks = [];

      mediaRecorder.ondataavailable = function (ev) {
        if (ev.data && ev.data.size > 0) {
          chunks.push(ev.data);
          const blob = new Blob(chunks, { type: mime });
          chunks = [];
          uploadBlob(blob);
        }
      };

      mediaRecorder.start(opts.chunkMs);
      active = true;
      status('whisper');
      return true;
    }

    function startWebSpeech() {
      const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
      if (!SR) throw new Error('No speech recognition available');
      recognition = new SR();
      recognition.continuous = true;
      recognition.interimResults = true;
      recognition.lang = opts.language === 'bn' ? 'bn-BD' : 'en-US';

      recognition.onresult = function (event) {
        let interim = '';
        let finalText = '';
        for (let i = event.resultIndex; i < event.results.length; i++) {
          const t = event.results[i][0].transcript;
          if (event.results[i].isFinal) finalText += t;
          else interim += t;
        }
        if (interim && typeof opts.onPartial === 'function') opts.onPartial(interim);
        if (finalText.trim() && typeof opts.onFinal === 'function') opts.onFinal(finalText.trim());
      };
      recognition.onerror = function (e) {
        if (typeof opts.onError === 'function') opts.onError(e.error || 'speech error');
      };
      recognition.onend = function () {
        if (active) {
          try { recognition.start(); } catch (e) {}
        }
      };
      recognition.start();
      active = true;
      status('webspeech');
      return true;
    }

    async function start() {
      if (active) return true;
      try {
        if (navigator.mediaDevices && window.MediaRecorder) {
          await startWhisper();
          return true;
        }
      } catch (e) {
        /* fall through to Web Speech */
      }
      startWebSpeech();
      return true;
    }

    function stop() {
      active = false;
      if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        try { mediaRecorder.stop(); } catch (e) {}
      }
      if (mediaStream) {
        mediaStream.getTracks().forEach(function (t) { t.stop(); });
        mediaStream = null;
      }
      if (recognition) {
        try { recognition.stop(); } catch (e) {}
        recognition = null;
      }
      mediaRecorder = null;
      status('idle');
    }

    return { start: start, stop: stop, isActive: function () { return active; } };
  }

  global.MedCoreAudioToText = { create: createAudioToText };
})(window);
