/**
 * MedCore AudioToText client
 * Complete WebM segments → Whisper API
 * + Web Speech for live text (and parse fallback)
 *
 * MediaRecorder timeslice fragments are NOT valid standalone WebM files.
 * We stop/restart every chunkMs so each upload is a complete file.
 */
(function (global) {
  function createAudioToText(options) {
    const opts = Object.assign({
      endpoint: '/api/audio-transcribe.php',
      mode: 'clinical',
      language: '',
      chunkMs: 4500,
      minBytes: 800,
      onPartial: null,
      onFinal: null,
      onError: null,
      onStatus: null,
    }, options || {});

    let mediaRecorder = null;
    let mediaStream = null;
    let recognition = null;
    let active = false;
    let segmentTimer = null;
    let mime = 'audio/webm';
    let segmentChunks = [];
    const uploadQueue = [];
    let pumping = false;
    let lastWhisperAt = 0;

    function status(msg) {
      if (typeof opts.onStatus === 'function') opts.onStatus(msg);
    }

    function fail(msg) {
      if (typeof opts.onError === 'function') opts.onError(msg);
    }

    function emitFinal(text, meta) {
      const t = String(text || '').trim();
      if (!t) return;
      if (typeof opts.onFinal === 'function') opts.onFinal(t, meta || {});
    }

    async function pumpUploads() {
      if (pumping) return;
      pumping = true;
      while (uploadQueue.length && active) {
        const blob = uploadQueue.shift();
        await uploadBlob(blob);
      }
      pumping = false;
    }

    function enqueueBlob(blob) {
      if (!blob || blob.size < opts.minBytes) {
        fail('Audio chunk too small (' + (blob ? blob.size : 0) + ' bytes) — speak closer to the mic.');
        return;
      }
      uploadQueue.push(blob);
      pumpUploads();
    }

    async function uploadBlob(blob) {
      status('uploading');
      const fd = new FormData();
      const ext = mime.indexOf('mp4') !== -1 ? 'mp4' : 'webm';
      fd.append('audio', blob, 'segment.' + ext);
      fd.append('mode', opts.mode);
      fd.append('refine', '1');
      if (opts.language) fd.append('language', opts.language);

      try {
        const res = await fetch(opts.endpoint, {
          method: 'POST',
          body: fd,
          credentials: 'same-origin',
        });
        let data = null;
        try {
          data = await res.json();
        } catch (e) {
          fail('Transcription API returned non-JSON (HTTP ' + res.status + ').');
          return;
        }
        if (data && data.success && data.text && String(data.text).trim()) {
          lastWhisperAt = Date.now();
          emitFinal(data.text, data);
          status('whisper');
        } else if (data && data.message) {
          fail(data.message);
        } else if (!res.ok) {
          fail('Transcription failed (HTTP ' + res.status + ').');
        } else {
          fail('No speech detected in this segment — keep talking.');
        }
      } catch (err) {
        fail(String(err && err.message ? err.message : err));
      }
    }

    function pickMime() {
      if (window.MediaRecorder && MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
        return 'audio/webm;codecs=opus';
      }
      if (window.MediaRecorder && MediaRecorder.isTypeSupported('audio/webm')) {
        return 'audio/webm';
      }
      if (window.MediaRecorder && MediaRecorder.isTypeSupported('audio/mp4')) {
        return 'audio/mp4';
      }
      return '';
    }

    function clearSegmentTimer() {
      if (segmentTimer) {
        clearTimeout(segmentTimer);
        segmentTimer = null;
      }
    }

    function scheduleSegmentEnd() {
      clearSegmentTimer();
      segmentTimer = setTimeout(function () {
        if (!active || !mediaRecorder) return;
        if (mediaRecorder.state === 'recording') {
          try { mediaRecorder.requestData(); } catch (e) {}
          try { mediaRecorder.stop(); } catch (e) {}
        }
      }, opts.chunkMs);
    }

    function bindRecorder() {
      segmentChunks = [];
      mediaRecorder = new MediaRecorder(mediaStream, mime ? { mimeType: mime } : undefined);

      mediaRecorder.ondataavailable = function (ev) {
        if (ev.data && ev.data.size > 0) segmentChunks.push(ev.data);
      };

      mediaRecorder.onerror = function () {
        fail('MediaRecorder error');
      };

      mediaRecorder.onstop = function () {
        const blob = new Blob(segmentChunks, { type: mime || 'audio/webm' });
        segmentChunks = [];
        if (active && blob.size > 0) {
          enqueueBlob(blob);
        }
        if (active && mediaStream && mediaStream.active !== false) {
          try {
            bindRecorder();
            mediaRecorder.start();
            scheduleSegmentEnd();
            status('whisper');
          } catch (e) {
            fail('Could not restart microphone capture: ' + e);
          }
        }
      };

      mediaRecorder.start();
      scheduleSegmentEnd();
    }

    async function startWhisper() {
      mime = pickMime();
      if (!window.MediaRecorder) {
        throw new Error('MediaRecorder not supported');
      }
      mediaStream = await navigator.mediaDevices.getUserMedia({
        audio: {
          echoCancellation: true,
          noiseSuppression: true,
          channelCount: 1,
        },
      });
      if (!mime) mime = 'audio/webm';
      active = true;
      bindRecorder();
      status('whisper');
      return true;
    }

    function startWebSpeech(asPrimary) {
      const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
      if (!SR) {
        if (asPrimary) throw new Error('No speech recognition available in this browser');
        return false;
      }
      if (recognition) return true;
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
        if (finalText.trim()) {
          if (typeof opts.onPartial === 'function') opts.onPartial(finalText.trim());
          // If Whisper has not returned recently, use Web Speech for parse/ledger
          if (asPrimary || Date.now() - lastWhisperAt > 6000) {
            emitFinal(finalText.trim(), { engine: 'webspeech' });
          }
        }
      };
      recognition.onerror = function (e) {
        if (asPrimary) fail(e.error || 'speech error');
      };
      recognition.onend = function () {
        if (active && recognition) {
          try { recognition.start(); } catch (e) {}
        }
      };
      recognition.start();
      if (asPrimary) {
        active = true;
        status('webspeech');
      }
      return true;
    }

    async function start() {
      if (active) return true;
      let whisperOk = false;
      try {
        if (navigator.mediaDevices && window.MediaRecorder) {
          await startWhisper();
          whisperOk = true;
        }
      } catch (e) {
        fail(String(e && e.message ? e.message : e));
      }
      // Always try Web Speech for live captions + fallback parse (Chrome/Edge)
      try {
        startWebSpeech(!whisperOk);
      } catch (e) {
        if (!whisperOk) throw e;
      }
      if (!active) active = true;
      return true;
    }

    function stop() {
      active = false;
      clearSegmentTimer();
      uploadQueue.length = 0;
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
      segmentChunks = [];
      status('idle');
    }

    return { start: start, stop: stop, isActive: function () { return active; } };
  }

  global.MedCoreAudioToText = { create: createAudioToText };
})(window);
