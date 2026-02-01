EPIC: Audio visualizer during track playback

Goals:
- Add visual interest during the listening phase
- Help players feel the music without Spotify's UI
- Work when sound is muted (visual-only mode)

Implementation:
1) Web Audio API setup:
   - Create AudioContext and AnalyserNode
   - Connect to <audio> element via createMediaElementSource
   - Use getByteFrequencyData() for spectrum data
2) Canvas visualizer component:
   - Render frequency bars or waveform
   - Options: bars (simple), circular (fancy), waveform (classic)
   - Animate at 30fps to save battery
3) Integration:
   - Add to RoundView below/behind the mystery card
   - Size: full-width, 60-80px height
   - Fade in when audio starts, fade out on pause/end
4) Styling:
   - Use CSS variables for colors (match glass theme)
   - Gradient fill for bars
   - Glow effect on peaks
5) Performance:
   - Use requestAnimationFrame with throttle
   - Pause animation when tab not visible
   - Fallback: static waveform image if AudioContext unavailable

Acceptance:
- Visualizer syncs to actual audio frequency
- Works on iOS Safari (AudioContext unlock on interaction)
- CPU usage under 5% on mobile
