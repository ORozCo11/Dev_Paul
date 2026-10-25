import { useEffect, useRef } from 'react';

// Plays the success check once. The player and animation load only when this
// mounts, so they stay out of the main bundle.
export default function SuccessCheck({ size = 140 }) {
  const container = useRef(null);
  useEffect(() => {
    let animation;
    let cancelled = false;
    Promise.all([import('lottie-web/build/player/lottie_light'), import('../assets/success-check.json')]).then(([{ default: lottie }, { default: animationData }]) => {
      if (cancelled || !container.current) return;
      animation = lottie.loadAnimation({ container: container.current, renderer: 'svg', loop: false, autoplay: true, animationData });
      // Respect reduced motion: show the finished check without playing it.
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) animation.goToAndStop(animation.totalFrames - 1, true);
    }).catch(() => {});
    return () => { cancelled = true; animation?.destroy(); };
  }, []);
  return <div ref={container} aria-hidden="true" style={{ width: size, height: size, margin: '0 auto' }} />;
}
