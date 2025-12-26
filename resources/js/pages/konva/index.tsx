import AppLayout from '@/layouts/app-layout';
import type Konva from 'konva';
import type { KonvaEventObject } from 'konva/lib/Node';
import { useRef, useState } from 'react';
import { Circle, Layer, Stage } from 'react-konva';
import { DroppedImage } from './types';
import URLImage from './URLImage';

const DropImage = () => {
    const dragUrl = useRef<string | null>(null);
    const stageRef = useRef<Konva.Stage | null>(null);
    const [images, setImages] = useState<DroppedImage[]>([]);
    const handleWheel = (e: KonvaEventObject<WheelEvent>) => {
        e.evt.preventDefault();

        const stage = stageRef.current;
        if (!stage) {
            return;
        }
        const oldScale = stage.scaleX();
        const pointer = stage.getPointerPosition();

        if (!pointer) {
            return;
        }
        const mousePointTo = {
            x: (pointer.x - stage.x()) / oldScale,
            y: (pointer.y - stage.y()) / oldScale,
        };

        // how to scale? Zoom in? Or zoom out?
        let direction = e.evt.deltaY > 0 ? 1 : -1;

        // when we zoom on trackpad, e.evt.ctrlKey is true
        // in that case lets revert direction
        if (e.evt.ctrlKey) {
            direction = -direction;
        }

        const scaleBy = 1.01;
        const newScale =
            direction > 0 ? oldScale * scaleBy : oldScale / scaleBy;

        stage.scale({ x: newScale, y: newScale });

        const newPos = {
            x: pointer.x - mousePointTo.x * newScale,
            y: pointer.y - mousePointTo.y * newScale,
        };
        stage.position(newPos);
    };
    return (
        <AppLayout>
            <div className="p-8">
                Try to drag and drop the image into the stage:
                <br />
                <img
                    alt="lion"
                    src="https://konvajs.org/assets/lion.png"
                    draggable="true"
                    onDragStart={(e) => {
                        const target = e.target as HTMLImageElement;
                        dragUrl.current = target.src;
                    }}
                />
                <div
                    onDrop={(e) => {
                        e.preventDefault();
                        stageRef.current?.setPointersPositions(e);
                        const pos = stageRef.current?.getPointerPosition();
                        if (!pos || !dragUrl.current) {
                            return;
                        }
                        const imageUrl = dragUrl.current;
                        setImages((prev) =>
                            prev.concat([
                                {
                                    x: pos.x,
                                    y: pos.y,
                                    src: imageUrl,
                                    id: `${Date.now()}-${prev.length + 1}`,
                                },
                            ]),
                        );
                    }}
                    onDragOver={(e) => e.preventDefault()}
                >
                    <Stage
                        width={window.innerWidth}
                        height={window.innerHeight}
                        style={{ border: '1px solid grey' }}
                        ref={stageRef}
                        onWheel={handleWheel}
                    >
                        <Layer>
                            <Circle
                                x={window.innerWidth / 2}
                                y={window.innerHeight / 2}
                                radius={70}
                                fill="red"
                                stroke="black"
                                strokeWidth={4}
                                draggable
                                onMouseEnter={() => {
                                    document.body.style.cursor = 'pointer';
                                }}
                                onMouseLeave={() => {
                                    document.body.style.cursor = 'default';
                                }}
                            />
                        </Layer>
                        <Layer>
                            {images.map((image) => (
                                <URLImage key={image.id} image={image} />
                            ))}
                        </Layer>
                    </Stage>
                </div>
            </div>
        </AppLayout>
    );
};

export default DropImage;
