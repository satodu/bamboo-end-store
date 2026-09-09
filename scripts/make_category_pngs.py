import os
import sys
import numpy as np
from PIL import Image

def process_image(jpg_path, png_path):
    if not os.path.exists(jpg_path):
        return
    img = Image.open(jpg_path).convert('RGBA')
    arr = np.array(img, dtype=np.float32)
    
    # Estimate background color from corners
    h, w, _ = arr.shape
    corners = np.array([
        arr[0, 0, :3], arr[0, 1, :3], arr[1, 0, :3],
        arr[0, w-1, :3], arr[0, w-2, :3], arr[1, w-1, :3],
        arr[h-1, 0, :3], arr[h-2, 0, :3], arr[h-1, 1, :3],
        arr[h-1, w-1, :3], arr[h-2, w-1, :3], arr[h-1, w-2, :3]
    ])
    bg = np.median(corners, axis=0)
    
    # Distance from background color
    diff = np.linalg.norm(arr[:, :, :3] - bg[:3], axis=2)
    
    # Smooth alpha transition
    alpha = np.clip((diff - 16) / 32, 0, 1) * 255
    arr[:, :, 3] = alpha
    
    out = Image.fromarray(arr.astype(np.uint8))
    out.save(png_path, 'PNG')
    print(f"Generated transparent PNG: {png_path}")

def main():
    cat_dir = 'public/images/categories'
    os.makedirs(cat_dir, exist_ok=True)
    
    if len(sys.argv) > 1:
        names = sys.argv[1:]
    else:
        names = [f[:-4] for f in os.listdir(cat_dir) if f.endswith('.jpg')]
        
    for name in names:
        jpg_path = os.path.join(cat_dir, f"{name}.jpg")
        png_path = os.path.join(cat_dir, f"{name}.png")
        process_image(jpg_path, png_path)

if __name__ == '__main__':
    main()
